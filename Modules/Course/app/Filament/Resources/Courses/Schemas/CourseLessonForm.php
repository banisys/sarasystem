<?php

namespace Modules\Course\Filament\Resources\Courses\Schemas;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Course\Integrations\CourseExamSource;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Nwidart\Modules\Facades\Module;

class CourseLessonForm
{
    public static function configure(Schema $schema, Course $course): Schema
    {
        return $schema->components([
            Section::make('اطلاعات درس')->columns(2)->columnSpanFull()->schema(self::fields($course)),
        ]);
    }

    private static function fields(Course $course): array
    {
        $inCourse = fn (Builder $query): Builder => $query->where('course_id', $course->getKey());

        return [
            TextInput::make('title')->label('عنوان')->trim()->required()->maxLength(255),
            Select::make('course_section_id')->label('سرفصل')->required()
                ->relationship('section', 'title', modifyQueryUsing: $inCourse)
                ->scopedExists(CourseSection::class, 'id', $inCourse),
            TextInput::make('position')->label('ترتیب')->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
            RichEditor::make('body_html')->label('متن درس')->maxLength(50000)
                ->disableToolbarButtons(['attachFiles'])
                ->formatStateUsing(fn (?string $state, ?CourseLesson $record): ?string => $state
                    ?? (filled($record?->body) ? '<p>'.nl2br(e($record->body), false).'</p>' : null))
                ->dehydrateStateUsing(fn (?string $state): string => str($state ?? '')->sanitizeHtml()->toString())
                ->columnSpanFull(),
            ...(Module::isEnabled('Exam') ? [
                TextInput::make('exam_code')->label('کد آزمون')->trim()->maxLength(64)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->helperText('کد یک آزمون مستقل از شعبهٔ فعلی یا اصلی را وارد کنید. پس از اتصال، ثبت‌نام دوره دسترسی به آزمون را تعیین می‌کند.')
                    ->formatStateUsing(fn (?CourseLesson $record): ?string => $record?->exists ? CourseExamSource::linkedCode($record) : null)
                    ->readOnly(fn (?CourseLesson $record): bool => $record?->exists && CourseExamSource::linkedCode($record) !== null)
                    ->dehydrated(false)
                    ->rule(fn (?CourseLesson $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (blank($value) || ($record?->exists && $value === CourseExamSource::linkedCode($record))) {
                            return;
                        }

                        if (($error = CourseExamSource::codeError((string) $value)) !== null) {
                            $fail($error);
                        }
                    })
                    ->saveRelationshipsUsing(function (CourseLesson $record, ?string $state, TextInput $component): void {
                        if (filled($state)) {
                            CourseExamSource::attachByCode($record, trim($state), $component->getStatePath());
                        }
                    }),
            ] : []),
            self::timedMedia('video', 'ویدئو', CourseLesson::VIDEO_TYPES),
            self::timedMedia('audio', 'فایل صوتی', CourseLesson::AUDIO_TYPES),
            SpatieMediaLibraryFileUpload::make('attachments')->label('فایل‌ها')->collection('attachments')
                ->disk('local')->visibility('private')->multiple()->preserveFilenames()->previewable(false)->maxFiles(5)->maxSize(12288),
            Toggle::make('is_preview')->label('پیش‌نمایش')->helperText('اعضای واردشده می‌توانند این درس را پیش از ثبت‌نام ببینند.'),
        ];
    }

    /** @param list<string> $types */
    private static function timedMedia(string $collection, string $label, array $types): SpatieMediaLibraryFileUpload
    {
        $isVideo = $collection === 'video';
        $durationColumn = $isVideo ? 'duration_minutes' : 'audio_duration_seconds';
        $lengthError = $isVideo
            ? 'مدت ویدئو قابل تشخیص نیست. یک فایل MP4 یا WebM معتبر بارگذاری کنید.'
            : 'مدت فایل صوتی قابل تشخیص نیست. یک فایل صوتی معتبر بارگذاری کنید.';
        $duration = null;

        return SpatieMediaLibraryFileUpload::make($collection)->label($label)->collection($collection)
            ->disk('local')->visibility('private')->acceptedFileTypes($types)->maxFiles(1)->previewable(false)->maxSize(12288)
            ->rule(function () use ($isVideo, $lengthError, &$duration): Closure {
                return function (string $attribute, TemporaryUploadedFile $file, Closure $fail) use ($isVideo, $lengthError, &$duration): void {
                    $duration = self::probeDuration($file, $isVideo);
                    if ($duration === null) {
                        $fail($lengthError);
                    }
                };
            })
            ->saveRelationshipsUsing(function (SpatieMediaLibraryFileUpload $component, CourseLesson $record) use ($collection, $durationColumn, $isVideo, $lengthError, &$duration): void {
                $file = collect($component->getRawState())->first();
                if ($file instanceof TemporaryUploadedFile) {
                    $duration ??= self::probeDuration($file, $isVideo);
                    if ($duration === null) {
                        throw ValidationException::withMessages([$component->getStatePath() => $lengthError]);
                    }
                }

                self::saveTimedMedia($component, $record, $collection, $durationColumn, $duration);
            });
    }

    private static function saveTimedMedia(SpatieMediaLibraryFileUpload $component, CourseLesson $record, string $collection, string $durationColumn, ?int $duration): void
    {
        CourseLesson::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
        $hadMedia = $record->load('media')->hasMedia($collection);

        if (collect($component->getRawState())->first() instanceof TemporaryUploadedFile) {
            // The single-file collection removes the old media after the replacement is stored.
            $component->saveUploadedFiles();
            if (blank($component->getRawState())) {
                throw ValidationException::withMessages([$component->getStatePath() => 'بارگذاری فایل کامل نشد. دوباره تلاش کنید.']);
            }

            $record->update([$durationColumn => $duration]);

            return;
        }

        $component->deleteAbandonedFiles();
        if ($hadMedia && ! $record->load('media')->hasMedia($collection)) {
            $record->update([$durationColumn => null]);
        }
    }

    private static function probeDuration(TemporaryUploadedFile $file, bool $isVideo): ?int
    {
        $local = tmpfile();
        if ($local === false) {
            return null;
        }

        try {
            $stream = $file->readStream();
            if (! is_resource($stream)) {
                return null;
            }

            try {
                if (stream_copy_to_stream($stream, $local) === false) {
                    return null;
                }
            } finally {
                fclose($stream);
            }

            $result = Process::timeout(10)->run([
                'ffprobe', '-v', 'error', '-protocol_whitelist', 'file', '-format_whitelist', $isVideo ? 'mov,matroska,webm' : 'mp3,mov,ogg,wav',
                '-select_streams', $isVideo ? 'V:0' : 'a:0', '-show_entries', 'stream=index:format=duration', '-of', 'json',
                '-i', stream_get_meta_data($local)['uri'],
            ]);
        } catch (ProcessTimedOutException) {
            return null;
        } finally {
            fclose($local);
        }

        $metadata = $result->successful() ? json_decode($result->output(), true) : null;
        $seconds = $metadata['format']['duration'] ?? null;
        if (empty($metadata['streams']) || ! is_numeric($seconds) || $seconds <= 0 || $seconds > ($isVideo ? 32767 * 60 : 2147483647)) {
            return null;
        }

        return (int) ceil($isVideo ? $seconds / 60 : $seconds);
    }
}

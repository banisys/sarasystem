<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Course\Enums\CourseLessonType;
use Modules\Course\Integrations\CourseExamSource;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Nwidart\Modules\Facades\Module;

class LessonsRelationManager extends CourseRelationManager
{
    protected static string $relationship = 'lessons';

    protected static ?string $title = 'درس‌ها';

    protected static ?string $modelLabel = 'درس';

    public function form(Schema $schema): Schema
    {
        $inCourse = fn (Builder $query): Builder => $query->where('course_id', $this->course()->getKey());

        return $schema->components([
            TextInput::make('title')->label('عنوان')->trim()->required()->maxLength(255),
            Select::make('type')->label('نوع')->options(CourseLessonType::class)
                ->default(CourseLessonType::File)->required()->live()
                ->disableOptionWhen(fn (string $value, ?CourseLesson $record): bool => $value === CourseLessonType::Exam->value
                    && $record?->type !== CourseLessonType::Exam
                    && ! Module::isEnabled('Exam')),
            Select::make('course_section_id')->label('بخش')->required()
                ->relationship('section', 'title', modifyQueryUsing: $inCourse)
                ->scopedExists(CourseSection::class, 'id', $inCourse),
            TextInput::make('position')->label('ترتیب')->integer()->minValue(0)->maxValue(1000000)->default(0)->required(),
            TextInput::make('duration_minutes')->label('مدت (دقیقه)')->integer()->minValue(1)->maxValue(32767),
            Textarea::make('body')->label('متن درس')->maxLength(50000)->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('video')->label('ویدئو')->collection('video')
                ->visible(fn (Get $get): bool => $get('type') === CourseLessonType::Video)
                ->disk('local')->visibility('private')->acceptedFileTypes(CourseLesson::VIDEO_TYPES)->previewable(false)->maxSize(12288),
            SpatieMediaLibraryFileUpload::make('attachments')->label('فایل‌ها')->collection('attachments')
                ->visible(fn (Get $get): bool => $get('type') === CourseLessonType::File)
                ->disk('local')->visibility('private')->multiple()->preserveFilenames()->previewable(false)->maxFiles(10)->maxSize(12288),
            Callout::make('آزمون')->key('examSettings')->info()->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('type') === CourseLessonType::Exam)
                ->description(fn (?CourseLesson $record): ?string => match (true) {
                    ! Module::isEnabled('Exam') => 'برای مدیریت آزمون، ماژول آزمون را فعال کنید.',
                    ! $record?->exists => 'فعالیت را ذخیره کنید، سپس از منوی همان ردیف «آزمون‌ها» را باز کنید.',
                    default => null,
                })
                ->actions([
                    Action::make('manageActivityExams')->label('مدیریت آزمون‌ها')
                        ->visible(fn (?CourseLesson $record): bool => ($record?->exists ?? false) && Module::isEnabled('Exam'))
                        ->url(fn (CourseLesson $record): string => CourseExamSource::managementUrl($record))
                        ->openUrlInNewTab(),
                ]),
            Toggle::make('is_preview')->label('پیش‌نمایش')->helperText('اعضای واردشده می‌توانند این درس را پیش از ثبت‌نام ببینند.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('title')->defaultSort('position')
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('type')->label('نوع')->badge(),
                TextColumn::make('section.title')->label('بخش'),
                TextColumn::make('position')->label('ترتیب')->sortable(),
                IconColumn::make('is_preview')->label('پیش‌نمایش')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->disabled(fn (): bool => ! $this->course()->sections()->exists())
                    ->tooltip('برای افزودن درس ابتدا یک بخش بسازید.'),
            ])
            ->recordActions([
                ...(Module::isEnabled('Exam') ? [
                    Action::make('exams')->label('آزمون‌ها')->url(CourseExamSource::managementUrl(...)),
                ] : []),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

<?php

namespace Modules\Course\Filament\Resources\Courses\Schemas;

use App\Filament\Forms\Components\TreeSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseEnrollmentSettings;
use Modules\Course\Models\CourseInstructor;
use Modules\Course\Support\CourseCategoryTree;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات کلی')->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label('عنوان')->trim()->required()->maxLength(255),
                TextInput::make('code')->label('کد')->trim()->maxLength(64)->scopedUnique()
                    ->helperText('در صورت خالی بودن، به‌صورت خودکار ساخته می‌شود.')->extraInputAttributes(['dir' => 'ltr']),
                Select::make('type')->label('نوع')->options(['normal' => 'عادی', 'package' => 'بسته'])
                    ->default('normal')->required(),
                TreeSelect::make('category_ids')->label('دسته‌بندی‌ها')->multiple()
                    ->nodes(fn (): array => CourseCategoryTree::current()->nodes())
                    ->helperText('اولین انتخاب، دسته‌بندی اصلی دوره است.'),
                Select::make('instructor_ids')->label('مدرسان')->multiple()->searchable()->visibleOn('create')
                    ->options(fn (): array => CourseInstructor::eligibleUsers()->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                    ->getSearchResultsUsing(fn (string $search): array => CourseInstructor::eligibleUsers()
                        ->whereLike('name', '%'.$search.'%')->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                    ->getOptionLabelsUsing(fn (array $values): array => CourseInstructor::eligibleUsers()
                        ->whereIntegerInRaw('id', $values)->pluck('name', 'id')->all()),
                TextInput::make('capacity')->label('ظرفیت')->integer()->minValue(1)->maxValue(2147483647),
                Toggle::make('is_published')->label('منتشرشده')->default(true),
                Toggle::make('allows_self_enrollment')
                    ->label(fn (): string => CourseEnrollmentSettings::current()->free_name)
                    ->helperText(fn (): string => CourseEnrollmentSettings::current()->free_enabled
                        ? 'با فعال کردن این گزینه، اعضای واردشده می‌توانند در دورهٔ منتشرشده ثبت‌نام کنند.'
                        : 'روش ثبت‌نام رایگان برای این شعبه غیرفعال است.'),
                RichEditor::make('description_html')->label('توضیحات')->maxLength(50000)
                    ->disableToolbarButtons(['attachFiles'])->columnSpanFull(),
            ]),
            Section::make('رسانه')->columns(2)->columnSpanFull()->schema([
                self::imageUpload('cover_image', 'تصویر کاور'),
                self::imageUpload('video_cover', 'کاور ویدیو'),
                FileUpload::make('intro_video')->label('ویدیوی معرفی')
                    ->acceptedFileTypes(['video/mp4', 'video/webm'])
                    ->disk('local')->directory('courses/intros')->visibility('private')
                    ->preventFilePathTampering()->maxSize(12288)->openable()->columnSpanFull(),
                self::imageUpload('gallery_images', 'گالری تصاویر')->multiple()->reorderable()->columnSpanFull(),
            ]),
            Section::make('زمان‌بندی')->columns(2)->columnSpanFull()->schema([
                DatePicker::make('starts_on')->label('تاریخ شروع')->jalali(),
                DatePicker::make('ends_on')->label('تاریخ پایان')->jalali()->afterOrEqual('starts_on'),
            ]),
            Section::make('قیمت‌گذاری')->columns(2)->columnSpanFull()->schema([
                Toggle::make('has_prepayment')->label('دارای پیش‌پرداخت')->live(),
                TextInput::make('prepayment_amount')->label('مبلغ پیش‌پرداخت')
                    ->integer()->minValue(1)->maxValue(999999999999)
                    ->visible(fn (Get $get): bool => (bool) $get('has_prepayment'))->required(),
            ]),
        ]);
    }

    public static function save(Course $course, array $data): Course
    {
        $categories = $data['category_ids'] ?? [];
        $data['course_category_id'] = array_shift($categories);
        $data['code'] = filled($data['code'] ?? null) ? $data['code'] : 'CRS-'.str()->upper(str()->random(16));
        $data['prepayment_amount'] = ($data['has_prepayment'] ?? false) ? $data['prepayment_amount'] : null;

        if (array_key_exists('description_html', $data)) {
            $html = str($data['description_html'] ?? '')->sanitizeHtml()->toString();
            $data['description_html'] = $html !== '' ? $html : null;
            $data['description'] = $html === ''
                ? null
                : self::plainDescription($html);
        }

        $course->fill($data)->save();
        $course->additionalCategories()->syncWithPivotValues($categories, ['tenant_id' => $course->tenant_id]);

        if ($course->wasRecentlyCreated) {
            $course->instructors()->createMany(
                array_map(fn (int|string $id): array => ['user_id' => $id], $data['instructor_ids'] ?? []),
            );
        }

        return $course;
    }

    private static function plainDescription(string $html): string
    {
        $withLineBreaks = preg_replace('~<br\s*/?>|</(?:p|li|h[1-6]|blockquote)>~i', "\n", $html);

        return trim(html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function imageUpload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)->label($label)->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'])
            ->disk('local')->directory('courses/images')->visibility('private')
            ->preventFilePathTampering()->maxSize(12288);
    }
}

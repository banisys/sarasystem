<?php

namespace Modules\Tutorial\Filament\Resources\SampleBatches\Schemas;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Term;
use Modules\School\Models\Grade;
use Modules\Tutorial\Services\ClassSampleGenerator;
use Modules\Tutorial\Support\SampleTexts;

/**
 * The «ساخت داده‌ی نمونه» form (ADR 0046): how much of each thing around
 * a class to make. Everything but the tenant has a default.
 */
class ClassSampleForm
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            ...ClassSampleGenerator::defaults(),
            'tenant_id' => Filament::getTenant()?->getKey(),
            'members' => ClassSampleGenerator::defaultMembers(),
        ];
    }

    /**
     * @return list<Section>
     */
    public static function components(): array
    {
        return [
            Section::make('کلاس')
                ->columns(3)
                ->schema(array_values(array_filter([
                    Select::make('tenant_id')
                        ->label(config('sarasystem.tenant_label'))
                        ->options(fn (): array => Tenant::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->required(),
                    TextInput::make('title')
                        ->label('عنوان کلاس')
                        ->placeholder('کلاس نمونه …')
                        ->maxLength(200),
                    Select::make('mode')
                        ->label('نوع برگزاری')
                        ->options(ClassMode::class)
                        ->selectablePlaceholder(false),
                    Select::make('term_id')
                        ->label('دوره‌ی آموزشی')
                        ->options(fn (): array => Term::options())
                        ->placeholder('یک دوره‌ی نمونه‌ی تازه')
                        ->helperText('خالی بماند: دوره‌ای هم‌بازه با کلاس ساخته می‌شود.'),
                    ClassSampleGenerator::enabled('School')
                        ? Select::make('grade_id')
                            ->label('پایه')
                            ->options(fn (): array => Grade::options())
                            ->placeholder('بدون پایه')
                            ->helperText('با پایه، پرونده‌ی تحصیلی فراگیران هم ساخته می‌شود.')
                        : null,
                    TextInput::make('capacity')
                        ->label('ظرفیت')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->placeholder('بدون سقف'),
                    TextInput::make('weeks_past')
                        ->label('هفته‌های گذشته')
                        ->helperText('چند هفته پیش شروع شده باشد؛ این جلسه‌ها سابقه دارند.')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(26)
                        ->placeholder('4'),
                    TextInput::make('weeks_ahead')
                        ->label('هفته‌های پیش رو')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(26)
                        ->placeholder('8'),
                    TextInput::make('classes')
                        ->label('تعداد کلاس')
                        ->helperText('هر کلاس فراگیران خودش را دارد و در ساعت دیگری از روز برگزار می‌شود.')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(ClassSampleGenerator::MAX_CLASSES)
                        ->placeholder('1')
                        ->live(onBlur: true),
                    Toggle::make('shared_staff')
                        ->label('مدرس و کادر مشترک')
                        ->helperText('همان مدرس و پشتیبان در همه‌ی کلاس‌ها؛ «کلاس‌های من» مدرس پر می‌شود.')
                        ->visible(fn (Get $get): bool => (int) $get('classes') > 1),
                    TextInput::make('sub_classes')
                        ->label('زیرکلاس')
                        ->helperText('فراگیران بین زیرکلاس‌ها پخش می‌شوند.')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(5)
                        ->placeholder('0'),
                ]))),
            Section::make('اعضا')
                ->description('برای هر نفر یک حساب تازه با نقش لازم در '.config('sarasystem.tenant_label').' ساخته می‌شود (بدون موبایل، پس پیامکی برایشان نمی‌رود). تعدادها برای هر کلاس است.')
                ->columns(3)
                ->schema([
                    ...ClassRole::definedRoles()->get()->map(fn (ClassRole $role): TextInput => TextInput::make('members.'.$role->getKey())
                        ->label($role->title)
                        ->integer()
                        ->minValue(0)
                        ->maxValue($role->is_attendee ? 200 : 10)
                        ->placeholder('0'))
                        ->all(),
                    TextInput::make('guardians')
                        ->label('ولی برای هر '.config('sarasystem.learner_label'))
                        ->helperText('۱: پدر، ۲: پدر و مادر')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(2)
                        ->placeholder('1'),
                    TextInput::make('password')
                        ->label('رمز عبور حساب‌ها')
                        ->helperText('خالی بماند: رمز تصادفی؛ با «ورود به‌جای کاربر» وارد شوید.')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->maxLength(100),
                ]),
            Section::make('درس‌ها و برنامه‌ی هفتگی')
                ->columns(3)
                ->schema([
                    TextInput::make('subjects')
                        ->label('تعداد درس')
                        ->helperText('هر درس برنامه‌ی هفتگی خودش را دارد ('.implode('، ', SampleTexts::SUBJECTS).').')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(count(SampleTexts::SUBJECTS))
                        ->placeholder('1'),
                    TextInput::make('sessions_per_week')
                        ->label('جلسه در هفته')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(6)
                        ->placeholder('2'),
                    TimePicker::make('start_time')
                        ->label('ساعت شروع')
                        ->helperText('جلسه‌های درس‌ها و کلاس‌ها پشت سر هم از این ساعت، با ۱۵ دقیقه فاصله؛ برای چند کلاس زودتر شروع کنید.')
                        ->seconds(false)
                        ->placeholder('16:00'),
                    TextInput::make('duration')
                        ->label('مدت هر جلسه')
                        ->suffix('دقیقه')
                        ->integer()
                        ->minValue(15)
                        ->maxValue(240)
                        ->placeholder('90'),
                    Toggle::make('teaching_plan')
                        ->label('طرح درس')
                        ->live(),
                    TextInput::make('topics')
                        ->label('سرفصل هر طرح درس')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(40)
                        ->placeholder('8')
                        ->visible(fn (Get $get): bool => (bool) $get('teaching_plan')),
                    TextInput::make('cancelled_sessions')
                        ->label('جلسه‌ی لغوشده با جبرانی')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(5)
                        ->placeholder('1'),
                ]),
            Section::make('سابقه‌ی جلسه‌های گذشته')
                ->columns(3)
                ->schema([
                    Toggle::make('attendance')
                        ->label('حضور و غیاب')
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('absent_percent')
                        ->label('غیبت')
                        ->suffix('٪')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100)
                        ->placeholder('10')
                        ->visible(fn (Get $get): bool => (bool) $get('attendance')),
                    TextInput::make('late_percent')
                        ->label('تأخیر')
                        ->suffix('٪')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100)
                        ->placeholder('5')
                        ->visible(fn (Get $get): bool => (bool) $get('attendance')),
                    Toggle::make('content')
                        ->label('مطالب جلسه و تکلیف')
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('homework_percent')
                        ->label('جلسه‌های با تکلیف')
                        ->suffix('٪')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100)
                        ->placeholder('50')
                        ->visible(fn (Get $get): bool => (bool) $get('content')),
                    TextInput::make('submission_percent')
                        ->label('تحویل تکلیف')
                        ->suffix('٪')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100)
                        ->placeholder('70')
                        ->visible(fn (Get $get): bool => (bool) $get('content')),
                    Toggle::make('grade_homework')
                        ->label('نمره‌ی تکلیف‌های تحویل‌شده')
                        ->visible(fn (Get $get): bool => (bool) $get('content')),
                ]),
        ];
    }
}

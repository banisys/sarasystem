<?php

namespace Modules\Class\Filament\Actions;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\Subject;
use Modules\Class\Models\TeachingPlan;
use Modules\Class\Services\SessionPlanner;

class TeachingScheduleActions
{
    public static function add(Classroom $classroom): Action
    {
        return Action::make('addSchedule')
            ->label('برنامه درسی')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->size(Size::Small)
            ->authorize(fn (): bool => self::canManage($classroom))
            ->modalHeading('افزودن برنامه درسی')
            ->modalWidth('3xl')
            ->fillForm(fn (): array => [
                'frequency' => Frequency::Weekly->value,
                'interval' => 1,
                'weekdays' => [],
                'monthly_by' => 'day',
                'mode' => $classroom->mode->value,
                'starts_on' => max($classroom->starts_on?->toDateString() ?? today()->toDateString(), today()->toDateString()),
                'ends_on' => $classroom->ends_on?->toDateString(),
            ])
            ->schema(fn (): array => self::fields($classroom))
            ->action(fn (array $data, SessionPlanner $planner) => SessionActions::attempt(
                fn () => $planner->saveSchedule(null, $classroom, self::normalize($data)),
                'برنامه ذخیره و جلسه‌ها ساخته شد.',
            ));
    }

    public static function edit(Classroom $classroom): Action
    {
        return Action::make('editSchedule')
            ->label('ویرایش')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->authorize(fn (ClassSchedule $record): bool => self::canManage($classroom)
                && $record->classroom_id === $classroom->getKey() && $record->tenant_id === $classroom->tenant_id)
            ->modalWidth('3xl')
            ->modalDescription('جلسه‌های گذشته و جلسه‌هایی که دستی تغییر کرده‌اند عوض نمی‌شوند.')
            ->fillForm(fn (ClassSchedule $record): array => [
                'teacher_ids' => $record->teachers->modelKeys(),
                ...$record->only(['subject_id', 'teaching_plan_id', 'interval', 'month_day', 'month_week', 'location']),
                'frequency' => $record->frequency->value,
                'mode' => ($record->mode ?? $classroom->mode)->value,
                'weekdays' => $record->weekdays,
                'month_weekday' => $record->weekdays[0] ?? null,
                'monthly_by' => $record->month_week !== null ? 'weekday' : 'day',
                'starts_on' => $record->starts_on->toDateString(),
                'ends_on' => $record->ends_on->toDateString(),
                'start_time' => substr((string) $record->start_time, 0, 5),
                'end_time' => substr((string) $record->end_time, 0, 5),
            ])
            ->schema(fn (): array => self::fields($classroom))
            ->action(fn (array $data, ClassSchedule $record, SessionPlanner $planner) => SessionActions::attempt(
                fn () => $planner->saveSchedule($record, $classroom, self::normalize($data)),
                'برنامه ذخیره و جلسه‌ها به‌روز شد.',
            ));
    }

    /**
     * @return list<mixed>
     */
    private static function fields(Classroom $classroom): array
    {
        $is = fn (Frequency ...$frequencies): \Closure => fn (Get $get): bool => in_array(self::frequency($get), $frequencies, true);
        $monthlyBy = fn (string $by): \Closure => fn (Get $get): bool => self::frequency($get) === Frequency::Monthly && $get('monthly_by') === $by;

        return [
            ToggleButtons::make('frequency')
                ->label('تکرار')
                ->options(Frequency::class)
                ->inline()
                ->required()
                ->live()
                ->columnSpanFull(),
            TextInput::make('interval')
                ->label(fn (Get $get): string => 'هر چند '.(self::frequency($get)?->unit() ?? '').' یک بار')
                ->numeric()
                ->minValue(1)
                ->maxValue(52)
                ->required()
                ->visible($is(Frequency::Daily, Frequency::Weekly, Frequency::Monthly)),
            CheckboxList::make('weekdays')
                ->label(fn (Get $get): string => self::frequency($get) === Frequency::Daily ? 'فقط در این روزها' : 'روزهای هفته')
                ->helperText(fn (Get $get): ?string => self::frequency($get) === Frequency::Daily ? 'خالی یعنی همه‌ی روزها.' : null)
                ->options(Weekday::class)
                ->columns(7)
                ->required($is(Frequency::Weekly))
                ->visible($is(Frequency::Daily, Frequency::Weekly))
                ->columnSpanFull(),
            Radio::make('monthly_by')
                ->label('در ماه')
                ->options(['day' => 'روز مشخصی از ماه', 'weekday' => 'روز مشخصی از هفته'])
                ->inline()
                ->live()
                ->visible($is(Frequency::Monthly)),
            TextInput::make('month_day')
                ->label('روز ماه')
                ->helperText('اگر ماه کوتاه‌تر باشد، روز آخر ماه.')
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->required($monthlyBy('day'))
                ->visible($monthlyBy('day')),
            Select::make('month_week')
                ->label('کدام')
                ->options([1 => 'اولین', 2 => 'دومین', 3 => 'سومین', 4 => 'چهارمین', -1 => 'آخرین'])
                ->required($monthlyBy('weekday'))
                ->visible($monthlyBy('weekday')),
            Select::make('month_weekday')
                ->label('روز هفته')
                ->options(Weekday::class)
                ->required($monthlyBy('weekday'))
                ->visible($monthlyBy('weekday')),
            DatePicker::make('starts_on')
                ->label(fn (Get $get): string => self::frequency($get) === Frequency::Once ? 'تاریخ' : 'از تاریخ')
                ->jalali()
                ->required(),
            DatePicker::make('ends_on')
                ->label('تا تاریخ')
                ->jalali()
                ->afterOrEqual('starts_on')
                ->required(fn (Get $get): bool => self::frequency($get) !== Frequency::Once)
                ->visible(fn (Get $get): bool => self::frequency($get) !== Frequency::Once),
            TimePicker::make('start_time')
                ->label('از ساعت')
                ->seconds(false)
                ->required(),
            TimePicker::make('end_time')
                ->label('تا ساعت')
                ->seconds(false)
                ->after('start_time')
                ->required(),
            Select::make('subject_id')
                ->label('درس')
                ->options(fn (?ClassSchedule $record): array => Subject::options($record?->subject_id))
                ->searchable()
                ->live(),
            Select::make('teaching_plan_id')
                ->label('طرح درس')
                ->helperText('سرفصل‌های آن به ترتیب به جلسه‌ها داده می‌شود.')
                ->options(fn (Get $get, ?ClassSchedule $record): array => TeachingPlan::options(filled($get('subject_id')) ? (int) $get('subject_id') : null, $record?->teaching_plan_id))
                ->searchable(),
            Select::make('teacher_ids')
                ->label('مدرس‌ها')
                ->helperText('یک یا چند نفر از اعضای کلاس که شرکت‌کننده نیستند؛ همه‌ی جلسه‌های برنامه را با هم درس می‌دهند.')
                ->multiple()
                ->options(fn (SessionPlanner $planner): array => $planner->teacherOptions($classroom)),
            SessionActions::modeField($classroom->mode),
            SessionActions::locationField()->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        $frequency = Frequency::from($data['frequency'] instanceof Frequency ? $data['frequency']->value : $data['frequency']);
        $byWeekday = $frequency === Frequency::Monthly && ($data['monthly_by'] ?? 'day') === 'weekday';

        return [
            ...$data,
            'frequency' => $frequency,
            'interval' => $frequency === Frequency::Once ? 1 : ($data['interval'] ?? 1),
            'weekdays' => match (true) {
                $byWeekday => [$data['month_weekday'] instanceof Weekday ? $data['month_weekday']->value : (int) $data['month_weekday']],
                in_array($frequency, [Frequency::Daily, Frequency::Weekly], true) => $data['weekdays'] ?? [],
                default => [],
            },
            'month_day' => $frequency === Frequency::Monthly && ! $byWeekday ? $data['month_day'] : null,
            'month_week' => $byWeekday ? (int) $data['month_week'] : null,
        ];
    }

    private static function frequency(Get $get): ?Frequency
    {
        $value = $get('frequency');

        return $value instanceof Frequency ? $value : Frequency::tryFrom((string) $value);
    }

    public static function canManage(Classroom $classroom): bool
    {
        return Tenant::current()?->getKey() === $classroom->tenant_id
            && Gate::allows('view', $classroom)
            && Gate::allows('update', $classroom);
    }
}

<?php

namespace Modules\Class\Filament\Resources\Classrooms\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\Subject;
use Modules\Class\Services\SessionPlanner;

/**
 * The «برنامه» tab of a class (ADR 0023): when it meets. Saving a schedule
 * builds its sessions (SessionPlanner); whoever may edit the class manages
 * its schedules.
 */
class SchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $title = 'برنامه';

    protected static ?string $modelLabel = 'برنامه';

    protected static ?string $pluralModelLabel = 'برنامه‌ها';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subject', 'teacher'])->withCount(['sessions' => fn (Builder $sessions) => $sessions->upcoming()->takingPlace()]))
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('frequency')
                    ->label('زمان')
                    ->formatStateUsing(fn (ClassSchedule $record): string => $record->summary()),
                TextColumn::make('subject.title')
                    ->label('درس')
                    ->placeholder('—'),
                TextColumn::make('teacher.name')
                    ->label('مدرس')
                    ->placeholder('—'),
                TextColumn::make('starts_on')
                    ->label('از')
                    ->jalaliDate(),
                TextColumn::make('ends_on')
                    ->label('تا')
                    ->jalaliDate(),
                TextColumn::make('location')
                    ->label('مکان')
                    ->placeholder('همان مکان کلاس')
                    ->toggleable(),
                TextColumn::make('sessions_count')
                    ->label('جلسه‌های پیش رو'),
            ])
            ->headerActions([
                Action::make('addSchedule')
                    ->label('افزودن برنامه')
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => $this->canManage())
                    ->modalWidth('3xl')
                    ->fillForm(fn (): array => [
                        'frequency' => Frequency::Weekly->value,
                        'interval' => 1,
                        'weekdays' => [],
                        'monthly_by' => 'day',
                        'starts_on' => max($this->classroom()->starts_on?->toDateString() ?? today()->toDateString(), today()->toDateString()),
                        'ends_on' => $this->classroom()->ends_on?->toDateString(),
                    ])
                    ->schema(fn (): array => $this->scheduleFields())
                    ->action(fn (array $data, SessionPlanner $planner) => SessionActions::attempt(
                        fn () => $planner->saveSchedule(null, $this->classroom(), self::normalize($data)),
                        'برنامه ذخیره و جلسه‌ها ساخته شد.',
                    )),
            ])
            ->recordActions([
                Action::make('editSchedule')
                    ->label('ویرایش')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (): bool => $this->canManage())
                    ->modalWidth('3xl')
                    ->modalDescription('جلسه‌های گذشته و جلسه‌هایی که دستی تغییر کرده‌اند عوض نمی‌شوند.')
                    ->fillForm(fn (ClassSchedule $record): array => [
                        ...$record->only(['subject_id', 'teacher_id', 'interval', 'month_day', 'month_week', 'location']),
                        'frequency' => $record->frequency->value,
                        'weekdays' => $record->weekdays,
                        'month_weekday' => $record->weekdays[0] ?? null,
                        'monthly_by' => $record->month_week !== null ? 'weekday' : 'day',
                        'starts_on' => $record->starts_on->toDateString(),
                        'ends_on' => $record->ends_on->toDateString(),
                        'start_time' => substr((string) $record->start_time, 0, 5),
                        'end_time' => substr((string) $record->end_time, 0, 5),
                    ])
                    ->schema(fn (): array => $this->scheduleFields())
                    ->action(fn (array $data, ClassSchedule $record, SessionPlanner $planner) => SessionActions::attempt(
                        fn () => $planner->saveSchedule($record, $this->classroom(), self::normalize($data)),
                        'برنامه ذخیره و جلسه‌ها به‌روز شد.',
                    )),
                Action::make('deleteSchedule')
                    ->label('حذف')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('جلسه‌های پیش روی این برنامه هم حذف می‌شوند؛ جلسه‌های گذشته و آن‌هایی که دستی تغییر کرده‌اند می‌مانند.')
                    ->visible(fn (): bool => $this->canManage())
                    ->action(fn (ClassSchedule $record, SessionPlanner $planner) => $planner->deleteSchedule($record)),
            ]);
    }

    /**
     * @return list<mixed>
     */
    private function scheduleFields(): array
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
                ->searchable(),
            Select::make('teacher_id')
                ->label('مدرس')
                ->helperText('از اعضای کلاس که شرکت‌کننده نیستند.')
                ->options(fn (SessionPlanner $planner): array => $planner->teacherOptions($this->classroom())),
            TextInput::make('location')
                ->label('مکان')
                ->helperText('خالی یعنی مکان خود کلاس.')
                ->maxLength(255)
                ->columnSpanFull(),
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

    private function canManage(): bool
    {
        return auth()->user()?->can('update', $this->classroom()) ?? false;
    }

    private function classroom(): Classroom
    {
        /** @var Classroom */
        return $this->getOwnerRecord();
    }
}

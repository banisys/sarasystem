<?php

namespace Modules\Class\Filament\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Identifiers;
use Ariaieboy\Jalali\CalendarUtils;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\AttendanceRegister;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\SessionPlanner;

/**
 * Columns and row actions of session lists: a class's «جلسه‌ها» tab and
 * «کلاس‌ها → جلسه‌ها» (ADR 0023). Changing a session needs the right to
 * edit its class; attendance follows AttendanceRegister (ADR 0024).
 */
class SessionActions
{
    /**
     * @return list<TextColumn>
     */
    public static function columns(bool $withClass): array
    {
        return array_values(array_filter([
            TextColumn::make('starts_at')
                ->label('زمان')
                ->formatStateUsing(fn (ClassSession $record): string => self::when($record))
                ->description(fn (ClassSession $record): ?string => $record->wasMoved() ? 'جابه‌جا از '.self::when($record, original: true) : null)
                ->sortable(),
            $withClass ? TextColumn::make('classroom.title')->label('کلاس') : null,
            TextColumn::make('subject.title')
                ->label('درس')
                ->placeholder('—'),
            TextColumn::make('teacher.name')
                ->label('مدرس')
                ->formatStateUsing(fn (ClassSession $record): string => $record->substituteTeacher
                    ? $record->substituteTeacher->name.' (جایگزین)'
                    : ($record->teacher?->name ?? '—'))
                ->placeholder('—'),
            TextColumn::make('location')
                ->label('مکان')
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('kind')
                ->label('نوع')
                ->badge()
                ->color('gray')
                ->toggleable(),
            TextColumn::make('status')
                ->label('وضعیت')
                ->badge()
                ->description(fn (ClassSession $record): ?string => $record->cancel_reason),
            TextColumn::make('attendance')
                ->label('حضور و غیاب')
                ->state(fn (ClassSession $record): ?string => ($record->attendances_count ?? 0) === 0 ? null
                    : "حاضر {$record->present_count} · غایب {$record->absent_count}")
                ->placeholder('—'),
        ]));
    }

    /**
     * Adds the counts the «حضور و غیاب» column shows.
     *
     * @param  Builder<ClassSession>  $query
     * @return Builder<ClassSession>
     */
    public static function withAttendanceCounts(Builder $query): Builder
    {
        return $query->withCount([
            'attendances',
            'attendances as present_count' => fn (Builder $attendances) => $attendances->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late]),
            'attendances as absent_count' => fn (Builder $attendances) => $attendances->whereIn('status', [AttendanceStatus::Absent, AttendanceStatus::Excused]),
        ]);
    }

    public static function recordActions(): ActionGroup
    {
        return ActionGroup::make([
            self::attendance(),
            self::move(),
            self::substitute(),
            self::cancel(),
            self::restore(),
            Action::make('deleteSession')
                ->label('حذف')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('فقط جلسه‌ی جبرانی یا فوق‌العاده‌ای که هنوز شروع نشده حذف می‌شود.')
                ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->schedule_id === null && ! $record->hasStarted())
                ->action(fn (ClassSession $record) => $record->delete()),
        ]);
    }

    /**
     * Runs a SessionPlanner operation and reports its outcome.
     */
    public static function attempt(callable $operation, string $success): void
    {
        try {
            $operation();
        } catch (ClassException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }

    /**
     * E.g. «سه‌شنبه ۱۴۰۵/۰۸/۱۲ · 08:00–09:30».
     */
    public static function when(ClassSession $session, bool $original = false): string
    {
        $start = $original ? $session->original_starts_at : $session->starts_at;
        $date = CalendarUtils::strftime('l Y/m/d', $start->toMutable());

        return $original ? $date.' '.$start->format('H:i') : $date.' · '.$start->format('H:i').'–'.$session->ends_at->format('H:i');
    }

    /**
     * The attendees with a status each, filled with what was recorded
     * (or «حاضر»).
     */
    private static function attendance(): Action
    {
        return Action::make('attendance')
            ->label('حضور و غیاب')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('success')
            ->visible(fn (ClassSession $record): bool => ($user = auth()->user()) instanceof User && app(AttendanceRegister::class)->canTake($user, $record))
            ->modalHeading(fn (ClassSession $record): string => 'حضور و غیاب · '.self::when($record))
            ->modalWidth('4xl')
            ->fillForm(function (ClassSession $record, AttendanceRegister $register): array {
                $recorded = $record->attendances()->get()->keyBy('user_id');

                return ['rows' => $register->attendees($record)->map(fn (User $user): array => [
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                    'status' => ($recorded[$user->getKey()]->status ?? AttendanceStatus::Present)->value,
                    'late_minutes' => $recorded[$user->getKey()]->late_minutes ?? null,
                    'note' => $recorded[$user->getKey()]->note ?? null,
                ])->values()->all()];
            })
            ->schema([
                Repeater::make('rows')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->columns(12)
                    ->schema([
                        Hidden::make('user_id'),
                        TextInput::make('name')->hiddenLabel()->disabled()->dehydrated(false)->columnSpan(3),
                        ToggleButtons::make('status')->hiddenLabel()->options(AttendanceStatus::class)->inline()->required()->live()->columnSpan(5),
                        TextInput::make('late_minutes')->hiddenLabel()->placeholder('دقیقه')->numeric()->minValue(1)->maxValue(600)
                            ->visible(fn (Get $get): bool => $get('status') === AttendanceStatus::Late->value || $get('status') === AttendanceStatus::Late)
                            ->columnSpan(1),
                        TextInput::make('note')->hiddenLabel()->placeholder('توضیح')->maxLength(255)->columnSpan(3),
                    ]),
            ])
            ->action(function (array $data, ClassSession $record, AttendanceRegister $register): void {
                /** @var User $user */
                $user = auth()->user();
                $rows = collect($data['rows'] ?? [])->keyBy(fn (array $row): int => (int) $row['user_id'])->all();

                self::attempt(fn () => $register->record($record, $rows, $user), 'حضور و غیاب ثبت شد.');
            });
    }

    private static function move(): Action
    {
        return Action::make('move')
            ->label('جابه‌جایی')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->status === SessionStatus::Scheduled && ! $record->hasStarted())
            ->fillForm(fn (ClassSession $record): array => [
                'date' => $record->starts_at->toDateString(),
                'start_time' => $record->starts_at->format('H:i'),
                'end_time' => $record->ends_at->format('H:i'),
                'location' => $record->location,
            ])
            ->schema([
                DatePicker::make('date')->label('تاریخ')->jalali()->required(),
                TimePicker::make('start_time')->label('از ساعت')->seconds(false)->required(),
                TimePicker::make('end_time')->label('تا ساعت')->seconds(false)->required(),
                TextInput::make('location')->label('مکان')->maxLength(255),
            ])
            ->modalDescription('جلسه‌ی جابه‌جاشده دیگر با تغییر برنامه‌ی کلاس عوض نمی‌شود.')
            ->action(fn (array $data, ClassSession $record, SessionPlanner $planner) => self::attempt(
                fn () => $planner->move($record, $data['date'], $data['start_time'], $data['end_time'], $data['location'] ?? null),
                'جلسه جابه‌جا شد.',
            ));
    }

    private static function substitute(): Action
    {
        return Action::make('substitute')
            ->label('مدرس جایگزین')
            ->icon(Heroicon::OutlinedUserCircle)
            ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->status !== SessionStatus::Cancelled)
            ->fillForm(fn (ClassSession $record): array => ['substitute_teacher_id' => $record->substitute_teacher_id])
            ->schema([
                Select::make('substitute_teacher_id')
                    ->label('مدرس جایگزین')
                    ->helperText('خالی یعنی خود مدرس جلسه.')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, ClassSession $record): array => self::searchTenantUsers($record, $search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->find($value)?->name),
            ])
            ->action(fn (array $data, ClassSession $record, SessionPlanner $planner) => self::attempt(
                fn () => $planner->setSubstitute($record, filled($data['substitute_teacher_id'] ?? null) ? (int) $data['substitute_teacher_id'] : null),
                'ذخیره شد.',
            ));
    }

    private static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('لغو جلسه')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->status === SessionStatus::Scheduled)
            ->schema([
                Textarea::make('reason')->label('دلیل')->required()->maxLength(255)->rows(2),
            ])
            ->action(fn (array $data, ClassSession $record, SessionPlanner $planner) => self::attempt(
                fn () => $planner->cancel($record, $data['reason']),
                'جلسه لغو شد.',
            ));
    }

    private static function restore(): Action
    {
        return Action::make('restore')
            ->label('برگرداندن جلسه')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->requiresConfirmation()
            ->visible(fn (ClassSession $record): bool => self::canChange($record) && $record->status === SessionStatus::Cancelled)
            ->action(fn (ClassSession $record, SessionPlanner $planner) => self::attempt(
                fn () => $planner->restore($record),
                'جلسه برگشت.',
            ));
    }

    private static function canChange(ClassSession $session): bool
    {
        return $session->classroom !== null && (auth()->user()?->can('update', $session->classroom) ?? false);
    }

    /**
     * @return array<int, string>
     */
    private static function searchTenantUsers(ClassSession $session, string $search): array
    {
        $search = Identifiers::toLatinDigits(trim($search));

        if ($search === '') {
            return [];
        }

        return User::query()
            ->inTenant(Tenant::query()->findOrFail($session->tenant_id))
            ->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('national_code', "%{$search}%")
                ->orWhereLike('mobile', "%{$search}%"))
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }
}

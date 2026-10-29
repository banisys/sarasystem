<?php

namespace Modules\Class\Filament\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Identifiers;
use Ariaieboy\Jalali\CalendarUtils;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\SessionPlanner;

/**
 * Columns and row actions of session lists: a class's «جلسه‌ها» tab and
 * «کلاس‌ها → جلسه‌ها» (ADR 0023). Changing a session needs the right to
 * edit its class.
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
        ]));
    }

    public static function recordActions(): ActionGroup
    {
        return ActionGroup::make([
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

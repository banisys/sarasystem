<?php

namespace Modules\Class\Services;

use App\Models\Tenant;
use App\Models\User;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;

/**
 * Calendar periods (a day, a Saturday-to-Friday week, a Jalali month or
 * year) and sessions laid out in them: a class's, for the class page's
 * «جلسه‌ها» tab (ADR 0047), or a teacher's, for their calendar from the
 * employees list (ADR 0048). «کلاس‌های من» uses the same periods (ADR 0026).
 */
class SessionCalendar
{
    public const VIEWS = ['day', 'week', 'month', 'year'];

    /**
     * Hours a day or week timeline shows at least.
     */
    private const FIRST_HOUR = 8;

    private const LAST_HOUR = 18;

    /**
     * The period of the view around the date and the dates of the periods
     * before and after it.
     *
     * @return array{first: CarbonImmutable, last: CarbonImmutable, previous: CarbonImmutable, next: CarbonImmutable, label: string}
     */
    public static function period(string $view, CarbonImmutable $date): array
    {
        $date = $date->startOfDay();

        switch ($view) {
            case 'day':
                return ['first' => $date, 'last' => $date, 'previous' => $date->subDay(), 'next' => $date->addDay(), 'label' => self::jalali($date, 'l j F Y')];

            case 'month':
                [$year, $month] = CalendarUtils::toJalali($date->year, $date->month, $date->day);
                $first = self::gregorian($year, $month, 1);
                $last = self::gregorian($year, $month, CalendarUtils::jalaliMonthLength($year, $month));
                [$previousYear, $previousMonth] = $month === 1 ? [$year - 1, 12] : [$year, $month - 1];

                return ['first' => $first, 'last' => $last, 'previous' => self::gregorian($previousYear, $previousMonth, 1), 'next' => $last->addDay(), 'label' => self::jalali($first, 'F Y')];

            case 'year':
                [$year] = CalendarUtils::toJalali($date->year, $date->month, $date->day);
                $first = self::gregorian($year, 1, 1);

                return ['first' => $first, 'last' => self::gregorian($year + 1, 1, 1)->subDay(), 'previous' => self::gregorian($year - 1, 1, 1), 'next' => self::gregorian($year + 1, 1, 1), 'label' => 'سال '.self::jalali($first, 'Y')];

            default:
                $first = $date->subDays(Weekday::from($date->dayOfWeek)->position());
                $last = $first->addDays(6);

                return ['first' => $first, 'last' => $last, 'previous' => $first->subWeek(), 'next' => $first->addWeek(), 'label' => self::jalali($first, 'j F').' تا '.self::jalali($last, 'j F Y')];
        }
    }

    /**
     * The class's sessions in the view around the date (see sessions()).
     *
     * @return array<string, mixed>
     */
    public function forClassroom(Classroom $classroom, string $view, CarbonImmutable $date): array
    {
        return [
            ...$this->sessions($classroom->sessions()->getQuery(), Tenant::query()->findOrFail($classroom->tenant_id), $view, $date),
            // Where the class meets; the same for all its sessions.
            'mode' => ['value' => $classroom->mode->value, 'label' => $classroom->mode->getLabel()],
            'shows_classroom' => false,
        ];
    }

    /**
     * The sessions the user teaches in the current tenant (ADR 0048), each
     * with its class.
     *
     * @return array<string, mixed>
     */
    public function forTeacher(User $teacher, string $view, CarbonImmutable $date): array
    {
        return [
            ...$this->sessions(ClassSession::query()->taughtBy($teacher), Tenant::current(), $view, $date),
            'mode' => null,
            'shows_classroom' => true,
        ];
    }

    /**
     * The sessions of the query in the view around the date. Day and week
     * give a timeline (hours and each session's place on it), month gives
     * a grid of days, year twelve small months with each day's sessions
     * counted by tone. The holidays are the tenant's.
     *
     * @param  Builder<ClassSession>  $query
     * @return array<string, mixed>
     */
    private function sessions(Builder $query, Tenant $tenant, string $view, CarbonImmutable $date): array
    {
        $view = in_array($view, self::VIEWS, true) ? $view : 'month';
        $period = self::period($view, $date);

        $sessions = (clone $query)
            ->whereBetween('starts_at', [$period['first']->startOfDay(), $period['last']->endOfDay()])
            ->with(['classroom', 'subject', 'teacher', 'substituteTeacher'])
            ->withCount('attendances')
            ->orderBy('starts_at')
            ->get();
        $holidays = $this->holidays($tenant, $period['first'], $period['last']);
        $byDay = $sessions->groupBy(fn (ClassSession $session): string => $session->starts_at->toDateString());

        $calendar = [
            'view' => $view,
            'date' => $date->toDateString(),
            'label' => $period['label'],
            'previous' => $period['previous']->toDateString(),
            'next' => $period['next']->toDateString(),
            'today' => today()->toDateString(),
            'weekdays' => array_map(fn (Weekday $day): string => $day->getLabel(), Weekday::cases()),
            'totals' => $this->totals($sessions),
            'durations' => $this->durations($sessions),
            // The nearest days with a session outside the period, to jump to.
            'previous_session' => (clone $query)->where('starts_at', '<', $period['first']->startOfDay())->max('starts_at'),
            'next_session' => (clone $query)->where('starts_at', '>', $period['last']->endOfDay())->min('starts_at'),
        ];
        $calendar['previous_session'] = $calendar['previous_session'] ? CarbonImmutable::parse($calendar['previous_session'])->toDateString() : null;
        $calendar['next_session'] = $calendar['next_session'] ? CarbonImmutable::parse($calendar['next_session'])->toDateString() : null;

        if ($view === 'year') {
            return [...$calendar, 'months' => $this->months($period['first'], $byDay, $holidays)];
        }

        $days = $this->days($period['first'], $period['last'], $byDay, $holidays);

        if ($view === 'month') {
            return [...$calendar, 'leading_blanks' => Weekday::from($period['first']->dayOfWeek)->position(), 'days' => $days];
        }

        [$fromHour, $toHour] = $this->hours($sessions);

        if ($view === 'day') {
            $calendar['mini_month'] = $this->miniMonth($query, $tenant, $date);
        }

        return [...$calendar, 'days' => array_map(fn (array $day): array => [
            ...$day,
            'sessions' => array_map(fn (array $card): array => [
                ...$card,
                'top' => round(($card['start_minute'] - $fromHour * 60) / (($toHour - $fromHour) * 60) * 100, 3),
                'height' => round(($card['end_minute'] - $card['start_minute']) / (($toHour - $fromHour) * 60) * 100, 3),
            ], $day['sessions']),
        ], $days), 'hours' => range($fromHour, $toHour - 1)];
    }

    /**
     * How a session shows: «برگزارشده», «لغوشده», «در حال برگزاری»,
     * «ثبت‌نشده» (its time is over but nobody recorded it) or upcoming.
     */
    public static function tone(ClassSession $session): string
    {
        return match (true) {
            $session->status === SessionStatus::Held => 'held',
            $session->status === SessionStatus::Cancelled => 'cancelled',
            $session->ends_at->isPast() => 'pending',
            $session->starts_at->isPast() => 'live',
            default => 'scheduled',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function toneLabels(): array
    {
        return [
            'scheduled' => 'پیش رو',
            'live' => 'در حال برگزاری',
            'pending' => 'گذشته، ثبت‌نشده',
            'held' => SessionStatus::Held->getLabel(),
            'cancelled' => SessionStatus::Cancelled->getLabel(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function card(ClassSession $session): array
    {
        $teacher = $session->substituteTeacher ?? $session->teacher;

        return [
            'id' => $session->getKey(),
            'time' => $session->starts_at->format('H:i').'–'.$session->ends_at->format('H:i'),
            'start_minute' => $session->starts_at->hour * 60 + $session->starts_at->minute,
            // A session ending after midnight is drawn to the end of its day.
            'end_minute' => $session->ends_at->isSameDay($session->starts_at) ? $session->ends_at->hour * 60 + $session->ends_at->minute : 24 * 60,
            'title' => $session->subject?->title ?? 'جلسه',
            'classroom' => $session->classroom?->title,
            'mode' => $session->classroom?->mode->value,
            'teacher' => $teacher?->name,
            'is_substitute' => $session->substituteTeacher !== null,
            'location' => $session->location,
            'tone' => self::tone($session),
            'tone_label' => self::toneLabels()[self::tone($session)],
            'kind' => $session->kind === SessionKind::Regular ? null : $session->kind->getLabel(),
            'was_moved' => $session->wasMoved(),
            'has_homework' => $session->homework !== null,
            'has_content' => $session->hasContent(),
            'has_attendance' => ($session->attendances_count ?? 0) > 0,
            'cancel_reason' => $session->cancel_reason,
        ];
    }

    /**
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<string, int>
     */
    private function totals(Collection $sessions): array
    {
        $counts = $sessions->countBy(fn (ClassSession $session): string => self::tone($session));

        return array_map(fn (string $tone): int => $counts[$tone] ?? 0, array_combine(array_keys(self::toneLabels()), array_keys(self::toneLabels())));
    }

    /**
     * The period's hours: held, and planned (all but the cancelled), as H:MM.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array{held: string, planned: string}
     */
    private function durations(Collection $sessions): array
    {
        $minutes = fn (Collection $sessions): int => (int) $sessions->sum(fn (ClassSession $session): float => $session->starts_at->diffInMinutes($session->ends_at));
        $format = fn (int $minutes): string => intdiv($minutes, 60).':'.sprintf('%02d', $minutes % 60);

        return [
            'held' => $format($minutes($sessions->where('status', SessionStatus::Held))),
            'planned' => $format($minutes($sessions->where('status', '!=', SessionStatus::Cancelled))),
        ];
    }

    /**
     * @return Collection<int, Holiday>
     */
    private function holidays(Tenant $tenant, CarbonImmutable $first, CarbonImmutable $last): Collection
    {
        return Holiday::applyingIn($tenant)
            ->whereDate('starts_on', '<=', $last)
            ->whereDate('ends_on', '>=', $first)
            ->get();
    }

    /**
     * @param  Collection<string, Collection<int, ClassSession>>  $byDay
     * @param  Collection<int, Holiday>  $holidays
     * @return list<array<string, mixed>>
     */
    private function days(CarbonImmutable $first, CarbonImmutable $last, Collection $byDay, Collection $holidays): array
    {
        $days = [];

        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'day_number' => (int) self::jalali($day, 'j'),
                'label' => self::jalali($day, 'l j F'),
                'is_today' => $day->isToday(),
                'is_friday' => $day->dayOfWeek === Weekday::Friday->value,
                'holiday' => $this->holidayOn($day, $holidays),
                'sessions' => ($byDay[$day->toDateString()] ?? collect())->map(fn (ClassSession $session): array => $this->card($session))->values()->all(),
            ];
        }

        return $days;
    }

    /**
     * The Jalali year's months, each day with its sessions counted by tone.
     *
     * @param  Collection<string, Collection<int, ClassSession>>  $byDay
     * @param  Collection<int, Holiday>  $holidays
     * @return list<array<string, mixed>>
     */
    private function months(CarbonImmutable $first, Collection $byDay, Collection $holidays): array
    {
        [$year] = CalendarUtils::toJalali($first->year, $first->month, $first->day);

        return array_map(fn (int $month): array => $this->month($year, $month, $byDay, $holidays), range(1, 12));
    }

    /**
     * The Jalali month of the day view's date, to pick another day from,
     * with the months before and after it.
     *
     * @param  Builder<ClassSession>  $query
     * @return array<string, mixed>
     */
    private function miniMonth(Builder $query, Tenant $tenant, CarbonImmutable $date): array
    {
        $period = self::period('month', $date);
        [$year, $month] = CalendarUtils::toJalali($date->year, $date->month, $date->day);
        $byDay = (clone $query)
            ->whereBetween('starts_at', [$period['first']->startOfDay(), $period['last']->endOfDay()])
            ->get()
            ->groupBy(fn (ClassSession $session): string => $session->starts_at->toDateString());
        $holidays = $this->holidays($tenant, $period['first'], $period['last']);

        return [
            ...$this->month($year, $month, $byDay, $holidays),
            'label' => $period['label'],
            'previous' => $period['previous']->toDateString(),
            'next' => $period['next']->toDateString(),
        ];
    }

    /**
     * @param  Collection<string, Collection<int, ClassSession>>  $byDay
     * @param  Collection<int, Holiday>  $holidays
     * @return array<string, mixed>
     */
    private function month(int $year, int $month, Collection $byDay, Collection $holidays): array
    {
        $start = self::gregorian($year, $month, 1);
        $days = [];

        for ($day = 1; $day <= CalendarUtils::jalaliMonthLength($year, $month); $day++) {
            $date = $start->addDays($day - 1);
            $sessions = $byDay[$date->toDateString()] ?? collect();

            $days[] = [
                'date' => $date->toDateString(),
                'day_number' => $day,
                'is_today' => $date->isToday(),
                'is_friday' => $date->dayOfWeek === Weekday::Friday->value,
                'holiday' => $this->holidayOn($date, $holidays),
                'count' => $sessions->count(),
                'tones' => $sessions->countBy(fn (ClassSession $session): string => self::tone($session))->all(),
            ];
        }

        return [
            'date' => $start->toDateString(),
            'label' => self::jalali($start, 'F'),
            'leading_blanks' => Weekday::from($start->dayOfWeek)->position(),
            'count' => array_sum(array_column($days, 'count')),
            'days' => $days,
        ];
    }

    /**
     * The timeline's hours: at least FIRST_HOUR to LAST_HOUR, widened to
     * fit every session.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array{int, int}
     */
    private function hours(Collection $sessions): array
    {
        $from = min([self::FIRST_HOUR, ...$sessions->map(fn (ClassSession $session): int => $session->starts_at->hour)->all()]);
        $to = max([self::LAST_HOUR, ...$sessions->map(fn (ClassSession $session): int => $session->ends_at->isSameDay($session->starts_at)
            ? $session->ends_at->hour + ($session->ends_at->minute > 0 ? 1 : 0)
            : 24)->all()]);

        return [$from, $to];
    }

    /**
     * @param  Collection<int, Holiday>  $holidays
     */
    private function holidayOn(CarbonImmutable $day, Collection $holidays): ?string
    {
        return $holidays->first(fn (Holiday $holiday): bool => $day->betweenIncluded($holiday->starts_on, $holiday->ends_on))?->title;
    }

    private static function gregorian(int $year, int $month, int $day): CarbonImmutable
    {
        [$gy, $gm, $gd] = CalendarUtils::toGregorian($year, $month, $day);

        return CarbonImmutable::create($gy, $gm, $gd)->startOfDay();
    }

    public static function jalali(CarbonImmutable $date, string $format): string
    {
        return CalendarUtils::strftime($format, $date->toMutable());
    }
}

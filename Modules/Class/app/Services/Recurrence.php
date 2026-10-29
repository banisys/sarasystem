<?php

namespace Modules\Class\Services;

use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonImmutable;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\ClassSchedule;

/**
 * The days a schedule meets on, between its first and last day (ADR 0023).
 * Weeks start on Saturday; months are Jalali months, counted from the
 * month of the first day. A day of the month past the month's end falls on
 * its last day. Holidays are left to SessionPlanner.
 */
class Recurrence
{
    /**
     * @return list<CarbonImmutable>
     */
    public function dates(ClassSchedule $schedule): array
    {
        $first = CarbonImmutable::parse($schedule->starts_on)->startOfDay();
        $last = CarbonImmutable::parse($schedule->ends_on)->startOfDay();
        $interval = max(1, $schedule->interval);
        $weekdays = $schedule->weekdays ?? [];

        if ($last->lessThan($first)) {
            return [];
        }

        return match ($schedule->frequency) {
            Frequency::Once => [$first],
            Frequency::Daily => $this->daily($first, $last, $interval, $weekdays),
            Frequency::Weekly => $this->weekly($first, $last, $interval, $weekdays),
            Frequency::Monthly => $this->monthly($first, $last, $interval, $schedule->month_day, $schedule->month_week, $weekdays[0] ?? null),
        };
    }

    /**
     * @param  list<int>  $weekdays  empty = every day
     * @return list<CarbonImmutable>
     */
    private function daily(CarbonImmutable $first, CarbonImmutable $last, int $interval, array $weekdays): array
    {
        $dates = [];

        for ($day = $first, $index = 0; $day->lessThanOrEqualTo($last); $day = $day->addDay(), $index++) {
            if ($index % $interval === 0 && ($weekdays === [] || in_array($day->dayOfWeek, $weekdays, true))) {
                $dates[] = $day;
            }
        }

        return $dates;
    }

    /**
     * @param  list<int>  $weekdays
     * @return list<CarbonImmutable>
     */
    private function weekly(CarbonImmutable $first, CarbonImmutable $last, int $interval, array $weekdays): array
    {
        $dates = [];
        $firstWeek = self::weekStart($first);

        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            $week = intdiv((int) $firstWeek->diffInDays(self::weekStart($day)), 7);

            if ($week % $interval === 0 && in_array($day->dayOfWeek, $weekdays, true)) {
                $dates[] = $day;
            }
        }

        return $dates;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function monthly(CarbonImmutable $first, CarbonImmutable $last, int $interval, ?int $monthDay, ?int $monthWeek, ?int $weekday): array
    {
        [$year, $month] = CalendarUtils::toJalali($first->year, $first->month, $first->day);
        $dates = [];

        for ($index = 0; ; $index++) {
            $monthStart = self::gregorian($year, $month, 1);

            if ($monthStart->greaterThan($last)) {
                break;
            }

            if ($index % $interval === 0) {
                $day = $monthWeek !== null && $weekday !== null
                    ? self::nthWeekday($year, $month, $monthWeek, $weekday)
                    : self::gregorian($year, $month, min($monthDay ?? 1, CalendarUtils::jalaliMonthLength($year, $month)));

                if ($day !== null && $day->betweenIncluded($first, $last)) {
                    $dates[] = $day;
                }
            }

            [$year, $month] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];
        }

        return $dates;
    }

    /**
     * The nth (1–4) or last (-1) given weekday of a Jalali month.
     */
    private static function nthWeekday(int $year, int $month, int $nth, int $weekday): ?CarbonImmutable
    {
        $length = CalendarUtils::jalaliMonthLength($year, $month);
        $matches = [];

        for ($day = 1; $day <= $length; $day++) {
            $date = self::gregorian($year, $month, $day);

            if ($date->dayOfWeek === $weekday) {
                $matches[] = $date;
            }
        }

        return $nth === -1 ? ($matches[count($matches) - 1] ?? null) : ($matches[$nth - 1] ?? null);
    }

    private static function gregorian(int $year, int $month, int $day): CarbonImmutable
    {
        [$gy, $gm, $gd] = CalendarUtils::toGregorian($year, $month, $day);

        return CarbonImmutable::create($gy, $gm, $gd)->startOfDay();
    }

    private static function weekStart(CarbonImmutable $day): CarbonImmutable
    {
        return $day->subDays(Weekday::from($day->dayOfWeek)->position());
    }
}

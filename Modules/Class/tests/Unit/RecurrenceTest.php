<?php

namespace Modules\Class\Tests\Unit;

use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonImmutable;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Services\Recurrence;
use Tests\TestCase;

/**
 * The days schedules meet on (ADR 0023).
 */
class RecurrenceTest extends TestCase
{
    public function test_once_is_its_first_day(): void
    {
        $this->assertSame(['2026-11-03'], $this->dates(Frequency::Once, '2026-11-03', '2026-11-03'));
    }

    public function test_daily_every_other_day_on_working_days(): void
    {
        // 2026-10-31 is a Saturday; Friday 2026-11-06 is left out.
        $dates = $this->dates(Frequency::Daily, '2026-10-31', '2026-11-08', interval: 2, weekdays: Weekday::workingDays());

        $this->assertSame(['2026-10-31', '2026-11-02', '2026-11-04', '2026-11-08'], $dates);
    }

    public function test_weekly_every_other_week_counts_weeks_from_saturday(): void
    {
        // Starts on a Wednesday: its week (from Saturday 10-31) is week 0.
        $dates = $this->dates(Frequency::Weekly, '2026-11-04', '2026-11-30', interval: 2, weekdays: [Weekday::Saturday->value, Weekday::Wednesday->value]);

        $this->assertSame(['2026-11-04', '2026-11-14', '2026-11-18', '2026-11-28'], $dates);
    }

    public function test_monthly_on_a_jalali_day_falls_back_to_the_months_last_day(): void
    {
        // From 1405/06/01: Shahrivar has 31 days, Mehr 30.
        $dates = $this->dates(Frequency::Monthly, self::g(1405, 6, 1), self::g(1405, 8, 30), monthDay: 31);

        $this->assertSame([self::g(1405, 6, 31), self::g(1405, 7, 30), self::g(1405, 8, 30)], $dates);
    }

    public function test_monthly_on_the_nth_and_last_weekday_every_other_month(): void
    {
        $second = $this->dates(Frequency::Monthly, self::g(1405, 7, 1), self::g(1405, 10, 30), interval: 2, weekdays: [Weekday::Tuesday->value], monthWeek: 2);
        $last = $this->dates(Frequency::Monthly, self::g(1405, 7, 1), self::g(1405, 7, 30), weekdays: [Weekday::Friday->value], monthWeek: -1);

        // Mehr and Azar (every other month from Mehr), on days 8–14.
        $this->assertSame([7, 9], array_map(fn (string $date): int => self::jalali($date)[1], $second));
        foreach ($second as $date) {
            $this->assertSame(Weekday::Tuesday->value, CarbonImmutable::parse($date)->dayOfWeek);
            $this->assertTrue(self::jalali($date)[2] >= 8 && self::jalali($date)[2] <= 14, "{$date} is not the second Tuesday.");
        }

        $friday = CarbonImmutable::parse($last[0]);
        $this->assertSame(Weekday::Friday->value, $friday->dayOfWeek);
        $this->assertGreaterThan(CarbonImmutable::parse(self::g(1405, 7, 30)), $friday->addWeek());
    }

    /**
     * @param  list<int>  $weekdays
     * @return list<string>
     */
    private function dates(Frequency $frequency, string $from, string $to, int $interval = 1, array $weekdays = [], ?int $monthDay = null, ?int $monthWeek = null): array
    {
        $schedule = new ClassSchedule([
            'frequency' => $frequency, 'interval' => $interval, 'weekdays' => $weekdays, 'month_day' => $monthDay, 'month_week' => $monthWeek,
            'starts_on' => $from, 'ends_on' => $to, 'start_time' => '08:00:00', 'end_time' => '09:00:00',
        ]);

        return array_map(fn (CarbonImmutable $date): string => $date->toDateString(), (new Recurrence)->dates($schedule));
    }

    /**
     * @return array{int, int, int}
     */
    private static function jalali(string $date): array
    {
        $day = CarbonImmutable::parse($date);

        return CalendarUtils::toJalali($day->year, $day->month, $day->day);
    }

    private static function g(int $year, int $month, int $day): string
    {
        return sprintf('%04d-%02d-%02d', ...CalendarUtils::toGregorian($year, $month, $day));
    }
}

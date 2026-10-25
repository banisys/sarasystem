<?php

namespace App\Rules;

use App\Support\Identifiers;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A typed Jalali date such as 1370/01/05 (Persian digits and "-" too),
 * optionally within a range of Jalali years. Until a Vue date picker is
 * chosen, public forms take dates as text.
 */
class JalaliDate implements ValidationRule
{
    public function __construct(
        public ?int $minYear = null,
        public ?int $maxYear = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = static::parse((string) $value);

        if ($parts === null) {
            $fail('تاریخ را به شکل ۱۳۷۰/۰۱/۰۵ وارد کنید.');

            return;
        }

        if (($this->minYear !== null && $parts[0] < $this->minYear) || ($this->maxYear !== null && $parts[0] > $this->maxYear)) {
            $fail("سال باید بین {$this->minYear} و {$this->maxYear} باشد.");
        }
    }

    /**
     * The Gregorian date of a valid Jalali date string, else null.
     */
    public static function toDate(?string $value): ?CarbonImmutable
    {
        $parts = static::parse((string) $value);

        if ($parts === null) {
            return null;
        }

        [$year, $month, $day] = CalendarUtils::toGregorian(...$parts);

        return CarbonImmutable::create($year, $month, $day);
    }

    /**
     * @return array{int, int, int}|null year, month, day
     */
    public static function parse(string $value): ?array
    {
        $value = str_replace('-', '/', Identifiers::toLatinDigits(trim($value)));

        if (! preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $value, $matches)) {
            return null;
        }

        [$year, $month, $day] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];

        return CalendarUtils::checkDate($year, $month, $day) ? [$year, $month, $day] : null;
    }
}

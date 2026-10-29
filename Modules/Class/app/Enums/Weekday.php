<?php

namespace Modules\Class\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Days of the week, valued as Carbon's dayOfWeek (0 = Sunday) and listed
 * from Saturday, as the Iranian week runs.
 */
enum Weekday: int implements HasLabel
{
    case Saturday = 6;

    case Sunday = 0;

    case Monday = 1;

    case Tuesday = 2;

    case Wednesday = 3;

    case Thursday = 4;

    case Friday = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::Saturday => 'شنبه',
            self::Sunday => 'یکشنبه',
            self::Monday => 'دوشنبه',
            self::Tuesday => 'سه‌شنبه',
            self::Wednesday => 'چهارشنبه',
            self::Thursday => 'پنجشنبه',
            self::Friday => 'جمعه',
        };
    }

    /**
     * Saturday to Thursday: the default working days.
     *
     * @return list<int>
     */
    public static function workingDays(): array
    {
        return [self::Saturday->value, self::Sunday->value, self::Monday->value, self::Tuesday->value, self::Wednesday->value, self::Thursday->value];
    }

    /**
     * Position in the week from Saturday (0) to Friday (6).
     */
    public function position(): int
    {
        return ($this->value + 1) % 7;
    }
}

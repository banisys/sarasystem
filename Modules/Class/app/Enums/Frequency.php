<?php

namespace Modules\Class\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a class schedule repeats (ADR 0023). Months are Jalali months.
 */
enum Frequency: string implements HasLabel
{
    case Once = 'once';

    case Daily = 'daily';

    case Weekly = 'weekly';

    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Once => 'یک بار',
            self::Daily => 'روزانه',
            self::Weekly => 'هفتگی',
            self::Monthly => 'ماهانه',
        };
    }

    /**
     * «روز» / «هفته» / «ماه», for «هر N …».
     */
    public function unit(): string
    {
        return match ($this) {
            self::Once => '',
            self::Daily => 'روز',
            self::Weekly => 'هفته',
            self::Monthly => 'ماه',
        };
    }
}

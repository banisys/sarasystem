<?php

namespace Modules\Student\Enums;

use Filament\Support\Contracts\HasLabel;

enum Nationality: string implements HasLabel
{
    case Iranian = 'iranian';

    /** Identified by a 12-digit foreign national code (کد اتباع). */
    case Foreign = 'foreign';

    public function getLabel(): string
    {
        return match ($this) {
            self::Iranian => 'ایرانی',
            self::Foreign => 'غیر ایرانی',
        };
    }

    public function nationalCodeLabel(): string
    {
        return match ($this) {
            self::Iranian => 'کد ملی',
            self::Foreign => 'کد اتباع',
        };
    }
}

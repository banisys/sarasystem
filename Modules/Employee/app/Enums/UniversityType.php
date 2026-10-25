<?php

namespace Modules\Employee\Enums;

use Filament\Support\Contracts\HasLabel;

enum UniversityType: string implements HasLabel
{
    case State = 'state';

    case Azad = 'azad';

    case PayameNoor = 'payame_noor';

    case AppliedScience = 'applied_science';

    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::State => 'سراسری',
            self::Azad => 'آزاد',
            self::PayameNoor => 'پیام نور',
            self::AppliedScience => 'علمی کاربردی',
            self::Other => 'سایر',
        };
    }
}

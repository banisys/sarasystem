<?php

namespace Modules\Employee\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmploymentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';

    case Suspended = 'suspended';

    case Left = 'left';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'مشغول به کار',
            self::Suspended => 'تعلیق',
            self::Left => 'پایان همکاری',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Left => 'gray',
        };
    }
}

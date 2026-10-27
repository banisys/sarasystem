<?php

namespace Modules\Student\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';

    case Suspended = 'suspended';

    case Graduated = 'graduated';

    /** Moved elsewhere or dropped out. */
    case Left = 'left';

    public function hasLeft(): bool
    {
        return in_array($this, [self::Graduated, self::Left], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Suspended => 'تعلیق',
            self::Graduated => 'دانش‌آموخته',
            self::Left => 'انصراف / انتقال',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Graduated => 'info',
            self::Left => 'gray',
        };
    }
}

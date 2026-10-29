<?php

namespace Modules\Class\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SessionStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';

    case Held = 'held';

    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'برنامه‌ریزی‌شده',
            self::Held => 'برگزارشده',
            self::Cancelled => 'لغوشده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Held => 'success',
            self::Cancelled => 'danger',
        };
    }
}

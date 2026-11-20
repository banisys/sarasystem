<?php

namespace Modules\Class\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How a session takes place: in person or online.
 */
enum ClassMode: string implements HasColor, HasLabel
{
    case InPerson = 'in_person';

    case Online = 'online';

    public function getLabel(): string
    {
        return match ($this) {
            self::InPerson => 'حضوری',
            self::Online => 'آنلاین',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InPerson => 'gray',
            self::Online => 'info',
        };
    }

    public function meetsOnline(): bool
    {
        return $this === self::Online;
    }

    public function meetsInPerson(): bool
    {
        return $this === self::InPerson;
    }
}

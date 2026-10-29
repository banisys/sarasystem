<?php

namespace Modules\Class\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a class meets: in a place, at its meeting link, or both.
 */
enum ClassMode: string implements HasColor, HasLabel
{
    case InPerson = 'in_person';

    case Online = 'online';

    case Blended = 'blended';

    public function getLabel(): string
    {
        return match ($this) {
            self::InPerson => 'حضوری',
            self::Online => 'آنلاین',
            self::Blended => 'ترکیبی',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InPerson => 'gray',
            self::Online => 'info',
            self::Blended => 'warning',
        };
    }

    public function meetsOnline(): bool
    {
        return $this !== self::InPerson;
    }

    public function meetsInPerson(): bool
    {
        return $this !== self::Online;
    }
}

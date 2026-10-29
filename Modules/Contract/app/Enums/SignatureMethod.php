<?php

namespace Modules\Contract\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How contracts of a type are signed (ADR 0020).
 */
enum SignatureMethod: string implements HasLabel
{
    case Electronic = 'electronic';

    case Paper = 'paper';

    case Either = 'either';

    public function getLabel(): string
    {
        return match ($this) {
            self::Electronic => 'الکترونیکی (با کد پیامکی)',
            self::Paper => 'کاغذی',
            self::Either => 'هر دو',
        };
    }

    public function allowsElectronic(): bool
    {
        return $this !== self::Paper;
    }

    public function allowsPaper(): bool
    {
        return $this !== self::Electronic;
    }
}

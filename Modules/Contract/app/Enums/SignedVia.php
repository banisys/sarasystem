<?php

namespace Modules\Contract\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a contract was actually signed.
 */
enum SignedVia: string implements HasLabel
{
    case Electronic = 'electronic';

    case Paper = 'paper';

    public function getLabel(): string
    {
        return match ($this) {
            self::Electronic => 'الکترونیکی',
            self::Paper => 'کاغذی',
        };
    }
}

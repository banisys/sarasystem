<?php

namespace Modules\Class\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Regular sessions come from a schedule; make-up and extra ones are added
 * by hand.
 */
enum SessionKind: string implements HasLabel
{
    case Regular = 'regular';

    case MakeUp = 'make_up';

    case Extra = 'extra';

    public function getLabel(): string
    {
        return match ($this) {
            self::Regular => 'عادی',
            self::MakeUp => 'جبرانی',
            self::Extra => 'فوق‌العاده',
        };
    }
}

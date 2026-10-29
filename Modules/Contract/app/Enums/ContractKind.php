<?php

namespace Modules\Contract\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a contract is to the one before it (ADR 0021): a contract of its
 * own, an amendment (الحاقیه) of a signed contract, or a renewal for the
 * period after a contract.
 */
enum ContractKind: string implements HasColor, HasLabel
{
    case Original = 'original';

    case Amendment = 'amendment';

    case Renewal = 'renewal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Original => 'قرارداد',
            self::Amendment => 'الحاقیه',
            self::Renewal => 'تمدید',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Original => 'gray',
            self::Amendment => 'info',
            self::Renewal => 'primary',
        };
    }
}

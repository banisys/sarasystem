<?php

namespace Modules\Contract\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A contract's life (ADR 0020): prepared, issued with its text fixed,
 * signed by the person, or cancelled after issuing.
 */
enum ContractStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';

    case Issued = 'issued';

    case Signed = 'signed';

    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Issued => 'در انتظار امضا',
            self::Signed => 'امضا شده',
            self::Cancelled => 'لغو شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'warning',
            self::Signed => 'success',
            self::Cancelled => 'danger',
        };
    }
}

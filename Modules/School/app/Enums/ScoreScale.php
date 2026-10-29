<?php

namespace Modules\School\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a stage's scores are given (ADR 0031): a number out of 20 (secondary
 * schools) or a descriptive level (primary school's توصیفی).
 */
enum ScoreScale: string implements HasLabel
{
    case Numeric = 'numeric';

    case Descriptive = 'descriptive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Numeric => 'عددی (از ۲۰)',
            self::Descriptive => 'توصیفی',
        };
    }
}

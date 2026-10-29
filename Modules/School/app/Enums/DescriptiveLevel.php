<?php

namespace Modules\School\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The four levels of descriptive assessment («ارزشیابی توصیفی») of a
 * primary school (ADR 0032).
 */
enum DescriptiveLevel: string implements HasColor, HasLabel
{
    case VeryGood = 'very_good';

    case Good = 'good';

    case Acceptable = 'acceptable';

    case NeedsEffort = 'needs_effort';

    public function getLabel(): string
    {
        return match ($this) {
            self::VeryGood => 'خیلی خوب',
            self::Good => 'خوب',
            self::Acceptable => 'قابل قبول',
            self::NeedsEffort => 'نیاز به تلاش بیشتر',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::VeryGood => 'success',
            self::Good => 'info',
            self::Acceptable => 'warning',
            self::NeedsEffort => 'danger',
        };
    }
}

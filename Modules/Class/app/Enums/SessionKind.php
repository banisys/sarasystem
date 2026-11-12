<?php

namespace Modules\Class\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Regular sessions come from a teaching schedule; other kinds are added by hand.
 */
enum SessionKind: string implements HasLabel
{
    case Regular = 'regular';

    case MakeUp = 'make_up';

    case Extra = 'extra';

    case Exam = 'exam';

    public function getLabel(): string
    {
        return match ($this) {
            self::Regular => 'عادی',
            self::MakeUp => 'جبرانی',
            self::Extra => 'فوق‌العاده',
            self::Exam => 'امتحانی',
        };
    }
}

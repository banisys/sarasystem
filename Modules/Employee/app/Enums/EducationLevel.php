<?php

namespace Modules\Employee\Enums;

use Filament\Support\Contracts\HasLabel;

enum EducationLevel: string implements HasLabel
{
    case BelowDiploma = 'below_diploma';

    case Diploma = 'diploma';

    case Associate = 'associate';

    case Bachelor = 'bachelor';

    case Master = 'master';

    case Doctorate = 'doctorate';

    /**
     * Levels earned at a university, whose university is asked.
     */
    public function isUniversity(): bool
    {
        return ! in_array($this, [self::BelowDiploma, self::Diploma], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::BelowDiploma => 'سیکل',
            self::Diploma => 'دیپلم',
            self::Associate => 'کاردانی',
            self::Bachelor => 'کارشناسی',
            self::Master => 'کارشناسی ارشد',
            self::Doctorate => 'دکتری',
        };
    }
}

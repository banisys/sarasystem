<?php

namespace Modules\Employee\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Military service status (نظام وظیفه); asked of Iranian men only.
 */
enum MilitaryStatus: string implements HasLabel
{
    case Completed = 'completed';

    case PermanentExemption = 'permanent_exemption';

    case EducationalExemption = 'educational_exemption';

    case Liable = 'liable';

    case NotLiable = 'not_liable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Completed => 'پایان خدمت',
            self::PermanentExemption => 'معافیت دائم',
            self::EducationalExemption => 'معافیت تحصیلی',
            self::Liable => 'مشمول',
            self::NotLiable => 'غیر مشمول',
        };
    }
}

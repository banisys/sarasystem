<?php

namespace Modules\Employee\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmploymentType: string implements HasLabel
{
    case FullTime = 'full_time';

    case PartTime = 'part_time';

    case Contract = 'contract';

    case Hourly = 'hourly';

    public function getLabel(): string
    {
        return match ($this) {
            self::FullTime => 'تمام‌وقت',
            self::PartTime => 'پاره‌وقت',
            self::Contract => 'قراردادی (پروژه‌ای)',
            self::Hourly => 'ساعتی (حق‌التدریس)',
        };
    }
}

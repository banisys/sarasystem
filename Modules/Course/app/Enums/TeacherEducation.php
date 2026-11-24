<?php

namespace Modules\Course\Enums;

use Filament\Support\Contracts\HasLabel;

enum TeacherEducation: int implements HasLabel
{
    case Diploma = 1;
    case Bachelor = 2;
    case Master = 3;
    case Doctorate = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::Diploma => 'دیپلم',
            self::Bachelor => 'کارشناسی',
            self::Master => 'کارشناسی ارشد',
            self::Doctorate => 'دکتری',
        };
    }
}

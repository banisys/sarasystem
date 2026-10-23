<?php

namespace Modules\Exam\Enums;

use Filament\Support\Contracts\HasLabel;

enum QuestionType: string implements HasLabel
{
    /** Pick one of several options; graded automatically. */
    case Choice = 'choice';

    /** A written answer; graded by staff. */
    case Descriptive = 'descriptive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Choice => 'چندگزینه‌ای',
            self::Descriptive => 'تشریحی',
        };
    }
}

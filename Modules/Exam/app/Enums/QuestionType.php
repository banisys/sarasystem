<?php

namespace Modules\Exam\Enums;

use Filament\Support\Contracts\HasLabel;

enum QuestionType: string implements HasLabel
{
    /** Pick one of several options; graded automatically. */
    case Choice = 'choice';

    case FillInTheBlank = 'fill_in_the_blank';

    /** A written answer; graded by staff. */
    case Descriptive = 'descriptive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Choice => 'چندگزینه‌ای',
            self::FillInTheBlank => 'جای خالی',
            self::Descriptive => 'تشریحی',
        };
    }
}

<?php

namespace Modules\Exam\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttemptStatus: string implements HasColor, HasLabel
{
    case InProgress = 'in_progress';

    /** Submitted, but some written answers are not graded yet. */
    case PendingReview = 'pending_review';

    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'در حال آزمون',
            self::PendingReview => 'در انتظار تصحیح',
            self::Completed => 'تصحیح‌شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'info',
            self::PendingReview => 'warning',
            self::Completed => 'success',
        };
    }
}

<?php

namespace Modules\Student\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a preregistration is in the admission process (ADR 0018). The
 * review itself is a workflow case (ADR 0016); a closing status sets closed_at.
 */
enum PreregistrationStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';

    /** Past the first step of its admission workflow. */
    case InReview = 'in_review';

    case Rejected = 'rejected';

    case Admitted = 'admitted';

    public function isClosed(): bool
    {
        return in_array($this, [self::Rejected, self::Admitted], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'ثبت‌شده',
            self::InReview => 'در حال بررسی',
            self::Rejected => 'رد شده',
            self::Admitted => 'پذیرفته شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::InReview => 'warning',
            self::Rejected => 'danger',
            self::Admitted => 'success',
        };
    }
}

<?php

namespace Modules\Employee\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a job application is in the hiring process (ADR 0014). Review steps
 * are added here as they are built; a closing status sets closed_at.
 */
enum ApplicationStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';

    case Rejected = 'rejected';

    case Hired = 'hired';

    public function isClosed(): bool
    {
        return in_array($this, [self::Rejected, self::Hired], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'ثبت‌شده',
            self::Rejected => 'رد شده',
            self::Hired => 'استخدام شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Rejected => 'danger',
            self::Hired => 'success',
        };
    }
}

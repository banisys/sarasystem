<?php

namespace App\Workflow;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a workflow case is (ADR 0016).
 */
enum CaseStatus: string implements HasColor, HasLabel
{
    case InProgress = 'in_progress';

    case Completed = 'completed';

    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::InProgress => 'در جریان',
            self::Completed => 'تأیید نهایی',
            self::Rejected => 'رد شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Rejected => 'danger',
        };
    }
}

<?php

namespace App\Workflow;

use Filament\Support\Contracts\HasLabel;

/**
 * What a step's assignee may do besides approving, claiming and writing a
 * note, which every step allows (ADR 0016).
 */
enum StepAction: string implements HasLabel
{
    case Reject = 'reject';

    case Return = 'return';

    case Edit = 'edit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Reject => 'رد کردن',
            self::Return => 'برگشت به مرحله‌ی قبل',
            self::Edit => 'ویرایش اطلاعات',
        };
    }
}

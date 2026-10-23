<?php

namespace Modules\Exam\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * When another attempt is allowed, within the exam's max_attempts.
 */
enum RetakePolicy: string implements HasLabel
{
    case Always = 'always';

    /** Only while the last attempt did not pass. */
    case FailedOnly = 'failed_only';

    public function getLabel(): string
    {
        return match ($this) {
            self::Always => 'همیشه',
            self::FailedOnly => 'فقط اگر قبول نشده باشد',
        };
    }
}

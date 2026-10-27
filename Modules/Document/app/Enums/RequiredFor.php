<?php

namespace Modules\Document\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * For whom a document type is required (ADR 0019).
 */
enum RequiredFor: string implements HasLabel
{
    case Nobody = 'nobody';

    case Everyone = 'everyone';

    /** Only for the chosen requirement owners, e.g. some job positions. */
    case Selected = 'selected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nobody => 'لازم نیست (اختیاری)',
            self::Everyone => 'برای همه لازم است',
            self::Selected => 'فقط برای موارد انتخاب‌شده',
        };
    }
}

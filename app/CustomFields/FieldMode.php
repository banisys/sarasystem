<?php

namespace App\CustomFields;

use Filament\Support\Contracts\HasLabel;

/**
 * Whether a form's built-in field is asked, and whether it must be
 * answered (ADR 0017).
 */
enum FieldMode: string implements HasLabel
{
    case Required = 'required';

    case Optional = 'optional';

    case Hidden = 'hidden';

    public function getLabel(): string
    {
        return match ($this) {
            self::Required => 'الزامی',
            self::Optional => 'اختیاری',
            self::Hidden => 'پرسیده نشود',
        };
    }
}

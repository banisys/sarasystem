<?php

namespace App\CustomFields;

use Filament\Support\Contracts\HasLabel;

/**
 * The kinds of fields an organization can add to a customizable form
 * (ADR 0017).
 */
enum FieldType: string implements HasLabel
{
    case Text = 'text';

    case Textarea = 'textarea';

    case Number = 'number';

    case Select = 'select';

    case MultiSelect = 'multi_select';

    case Checkbox = 'checkbox';

    case Date = 'date';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'متن کوتاه',
            self::Textarea => 'متن بلند',
            self::Number => 'عدد',
            self::Select => 'انتخاب یک گزینه',
            self::MultiSelect => 'انتخاب چند گزینه',
            self::Checkbox => 'تیک (بله / خیر)',
            self::Date => 'تاریخ',
        };
    }

    public function hasOptions(): bool
    {
        return $this === self::Select || $this === self::MultiSelect;
    }
}

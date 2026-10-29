<?php

namespace Modules\Class\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';

    case Late = 'late';

    case Absent = 'absent';

    case Excused = 'excused';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'حاضر',
            self::Late => 'تأخیر',
            self::Absent => 'غایب',
            self::Excused => 'غیبت موجه',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
            self::Excused => 'gray',
        };
    }

    public function wasThere(): bool
    {
        return $this === self::Present || $this === self::Late;
    }
}

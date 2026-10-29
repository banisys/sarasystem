<?php

namespace App\Guardians;

use Filament\Support\Contracts\HasLabel;

enum GuardianRelationship: string implements HasLabel
{
    case Father = 'father';

    case Mother = 'mother';

    case LegalGuardian = 'legal_guardian';

    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Father => 'پدر',
            self::Mother => 'مادر',
            self::LegalGuardian => 'سرپرست قانونی',
            self::Other => 'سایر',
        };
    }
}

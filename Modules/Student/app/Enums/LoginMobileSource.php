<?php

namespace Modules\Student\Enums;

use App\Guardians\GuardianRelationship;
use Filament\Support\Contracts\HasLabel;

/**
 * Whose mobile a learner signs in with (ADR 0028): their own, or a
 * parent's when they have none.
 */
enum LoginMobileSource: string implements HasLabel
{
    case Learner = 'learner';

    case Father = 'father';

    case Mother = 'mother';

    public function getLabel(): string
    {
        return match ($this) {
            self::Learner => 'موبایل خود '.config('sarasystem.learner_label'),
            self::Father => 'موبایل پدر',
            self::Mother => 'موبایل مادر',
        };
    }

    public function relationship(): ?GuardianRelationship
    {
        return match ($this) {
            self::Learner => null,
            self::Father => GuardianRelationship::Father,
            self::Mother => GuardianRelationship::Mother,
        };
    }
}

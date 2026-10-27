<?php

namespace App\Workflow;

use Filament\Support\Contracts\HasLabel;

/**
 * Who a step goes to: everyone holding a role in the step's tenant (one of
 * them is enough), or one person.
 */
enum AssigneeType: string implements HasLabel
{
    case Role = 'role';

    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::Role => 'دارندگان یک نقش',
            self::User => 'یک شخص',
        };
    }
}

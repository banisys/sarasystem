<?php

namespace App\Campus;

use App\Models\User;

/**
 * Where the staff pyramid gets the current tenant's people (ADR 0058).
 */
interface StaffSource
{
    public function canView(User $user): bool;

    /**
     * @return list<array{user_id: int, name: string, title: ?string, url: ?string}>
     */
    public function people(User $user): array;
}

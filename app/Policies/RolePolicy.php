<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Role definitions are shared by all tenants, so only the super admin
 * (through Gate::before) manages them; see docs/decisions/0003-role-assignment-rules.md.
 * RoleResource is excluded from Shield's permission generation.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Role $role): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Role $role): bool
    {
        return false;
    }

    public function delete(User $user, Role $role): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

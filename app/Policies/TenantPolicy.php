<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Tenants are installation-level data: only the super admin manages them,
 * and that happens through Gate::before. No role grants these abilities,
 * so TenantResource is excluded from Shield's permission generation.
 * Deactivating is the normal way to retire a tenant; deleting is allowed
 * only for one without data (TenantResource, Tenant::deleteWithAccess()).
 */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

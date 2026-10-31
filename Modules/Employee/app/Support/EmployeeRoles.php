<?php

namespace Modules\Employee\Support;

use App\Authorization\RoleAssignment;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Support\Collection;

/**
 * The roles a new employee can be given in a tenant: those the acting user
 * may assign there (ADR 0003), without super admin and without the panel
 * role, which forms offer as its own «دسترسی به پنل مدیریت» toggle.
 */
class EmployeeRoles
{
    /**
     * @return Collection<int, Role>
     */
    public static function assignable(User $actor, Tenant $tenant): Collection
    {
        return RoleAssignment::assignableRoles($actor, $tenant)
            ->reject(fn (Role $role): bool => $role->name === Utils::getPanelUserRoleName() || $role->name === Role::SUPER_ADMIN)
            ->values();
    }

    /**
     * @return array<int, string> role id => label
     */
    public static function options(User $actor, Tenant $tenant): array
    {
        return static::assignable($actor, $tenant)
            ->mapWithKeys(fn (Role $role): array => [$role->getKey() => $role->display_name])
            ->all();
    }
}

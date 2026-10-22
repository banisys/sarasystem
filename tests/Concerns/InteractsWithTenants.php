<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;

trait InteractsWithTenants
{
    /**
     * Give the user a (global) role inside one tenant.
     */
    protected function grantRole(User $user, Tenant $tenant, string $role): void
    {
        $previousTeam = getPermissionsTeamId();

        setPermissionsTeamId(null);
        Role::findOrCreate($role, 'web');

        setPermissionsTeamId($tenant->getKey());
        $user->unsetRelation('roles')->assignRole($role);

        setPermissionsTeamId($previousTeam);
        $user->unsetRelation('roles');
    }

    /**
     * Make the user admin-panel staff in the tenant.
     */
    protected function makeStaff(User $user, Tenant $tenant): void
    {
        $this->grantRole($user, $tenant, Utils::getPanelUserRoleName());
    }
}

<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;

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
     * Give a (global) role the given permissions, creating both if needed.
     *
     * @param  list<string>  $permissions  e.g. ['ViewAny:User', 'Create:User']
     */
    protected function roleWithPermissions(string $role, array $permissions): Role
    {
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        $model = Role::findOrCreate($role, 'web');
        $model->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $permissions));

        setPermissionsTeamId($previousTeam);

        return $model;
    }

    /**
     * Act inside the admin panel of the tenant, as Filament and the tenant
     * middleware would set it up for a request.
     */
    protected function actingInPanel(User $user, Tenant $tenant): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($tenant);
        $tenant->makeCurrent();
        $user->unsetRelation('roles')->unsetRelation('permissions');
    }

    /**
     * Make the user admin-panel staff in the tenant.
     */
    protected function makeStaff(User $user, Tenant $tenant): void
    {
        $this->grantRole($user, $tenant, Utils::getPanelUserRoleName());
    }
}

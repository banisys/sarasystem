<?php

namespace Database\Seeders\Concerns;

use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * For module demo seeders: give the core demo roles (DemoSeeder) the
 * module's permissions. Adds only, so running it again is safe; running
 * the core DemoSeeder again resets the roles to its own permissions.
 */
trait GrantsDemoPermissions
{
    /**
     * @param  array<string, list<string>>  $permissions  role name => e.g. ['ViewAny:Contract']
     */
    protected function grantDemoPermissions(array $permissions): void
    {
        foreach ($permissions as $roleName => $names) {
            $role = Role::query()->whereNull('tenant_id')->where('name', $roleName)->first();

            $role?->givePermissionTo(array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), $names));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

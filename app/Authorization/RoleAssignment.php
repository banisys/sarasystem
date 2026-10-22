<?php

namespace App\Authorization;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Who may hand out which roles, and who may manage whom.
 * The permission-subset rule from docs/decisions/0003-role-assignment-rules.md:
 * you may only assign a role whose permissions you all have yourself.
 */
class RoleAssignment
{
    /**
     * Roles the actor may assign in the tenant.
     *
     * @return Collection<int, Role>
     */
    public static function assignableRoles(User $actor, Tenant $tenant): Collection
    {
        $roles = Role::query()->assignableIn($tenant)->with('permissions')->orderBy('name')->get();

        if ($actor->is_super_admin) {
            return $roles;
        }

        $own = static::permissionIdsIn($actor, $tenant);

        return $roles->filter(fn (Role $role): bool => $role->permissions->pluck('id')->diff($own)->isEmpty())->values();
    }

    /**
     * @return list<int>
     */
    public static function assignableRoleIds(User $actor, Tenant $tenant): array
    {
        return static::assignableRoles($actor, $tenant)->modelKeys();
    }

    /**
     * Whether the actor may edit the target's account, change their roles in
     * the tenant or remove them from it. Every role the target holds in any
     * tenant must be assignable by the actor here, because identity fields
     * are shared by all tenants.
     */
    public static function canManage(User $actor, User $target, Tenant $tenant): bool
    {
        if ($actor->is_super_admin) {
            return true;
        }

        if ($target->is_super_admin) {
            return false;
        }

        $targetRoleIds = DB::table(config('permission.table_names.model_has_roles'))
            ->where(config('permission.column_names.model_morph_key'), $target->getKey())
            ->where('model_type', $target->getMorphClass())
            ->distinct()
            ->pluck('role_id');

        return $targetRoleIds->diff(static::assignableRoleIds($actor, $tenant))->isEmpty();
    }

    /**
     * Ids of the actor's effective permissions (via roles and directly) in the tenant.
     *
     * @return SupportCollection<int, int>
     */
    private static function permissionIdsIn(User $actor, Tenant $tenant): SupportCollection
    {
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($tenant->getKey());

        try {
            return $actor->unsetRelation('roles')->unsetRelation('permissions')->getAllPermissions()->pluck('id');
        } finally {
            setPermissionsTeamId($previousTeam);
            $actor->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}

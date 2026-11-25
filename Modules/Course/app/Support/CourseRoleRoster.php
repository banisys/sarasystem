<?php

namespace Modules\Course\Support;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CourseRoleRoster
{
    private const string ALL = 'all';

    /** @return array<int|string, string> */
    public function tenantOptions(User $actor): array
    {
        $primary = Tenant::primary();
        if ($actor->isSuperAdmin() || ($primary !== null && $actor->canAccessTenant($primary) && $this->canBrowseUsers($actor, $primary))) {
            return [self::ALL => 'همهٔ '.config('sarasystem.tenant_plural_label')]
                + Tenant::query()->orderBy('name')->pluck('name', 'id')->all();
        }

        return $actor->staffTenants()->active()->orderBy('name')->get(['tenants.id', 'tenants.name'])
            ->filter(fn (Tenant $tenant): bool => $this->canBrowseUsers($actor, $tenant))
            ->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    public function roleOptions(User $actor, mixed $scope): array
    {
        return $this->roles($actor, $scope)?->orderBy('label')->orderBy('name')->get()
            ->mapWithKeys(fn (Role $role): array => [
                $role->id => $role->display_name.($role->tenant_id === null ? '' : ' (ویژهٔ '.config('sarasystem.tenant_label').')'),
            ])->all() ?? [];
    }

    /** @return Builder<User>|null */
    public function users(User $actor, mixed $scope, int $roleId): ?Builder
    {
        if (! $this->roles($actor, $scope)?->whereKey($roleId)->exists()) {
            return null;
        }

        $users = User::query();
        $assignments = DB::table(config('permission.table_names.model_has_roles'))
            ->select(config('permission.column_names.model_morph_key'))
            ->where('model_type', $users->getModel()->getMorphClass())
            ->where('role_id', $roleId)
            ->when($scope !== self::ALL, fn ($query) => $query->where('tenant_id', (int) $scope));

        return $users->whereIn('id', $assignments);
    }

    private function canBrowseUsers(User $actor, Tenant $tenant): bool
    {
        return $tenant->execute(fn (): bool => (clone $actor)
            ->unsetRelation('roles')->unsetRelation('permissions')->can('ViewAny:User'));
    }

    /** @return Builder<Role>|null */
    private function roles(User $actor, mixed $scope): ?Builder
    {
        if ((! is_int($scope) && ! is_string($scope)) || ! isset($this->tenantOptions($actor)[$scope])) {
            return null;
        }

        $roles = Role::query()->where('guard_name', 'web');
        if ($scope === self::ALL) {
            return $roles->whereNull('tenant_id');
        }

        $tenant = Tenant::query()->find($scope);

        return $tenant === null ? null : $roles->assignableIn($tenant);
    }
}

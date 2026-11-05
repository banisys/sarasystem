<?php

namespace Modules\Campus\Services;

use App\Campus\StaffPeople;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * The current tenant's people by their power there (ADR 0058): what they can do in
 * this tenant's admin panel, from their roles and direct permissions here. People
 * who can do exactly the same stand together. The scene draws each person.
 */
class StaffHierarchy
{
    public function __construct(private PowerOrder $order) {}

    /**
     * @return array{roles: list<array<string, mixed>>, levels: int, totalPermissions: int}
     */
    public function scene(User $viewer): array
    {
        $tenant = Tenant::current();
        $people = collect(StaffPeople::for($viewer));
        $userIds = $people->pluck('user_id')->all();
        $allPermissions = Permission::query()->pluck('name', 'id');

        $assignments = $this->assignments($userIds, $tenant);
        $direct = $this->directPermissions($userIds, $tenant);
        $roles = Role::query()->with('permissions:id,name')->whereIn('id', $assignments->flatten()->unique())->get()->keyBy(fn (Role $role): int => $role->getKey());
        $supers = $this->superAdmins($userIds);
        $panelRoleName = Utils::getPanelUserRoleName();

        $groups = [];
        foreach ($people as $person) {
            $id = $person['user_id'];
            $held = collect($assignments[$id] ?? [])->map(fn (int $roleId): Role => $roles[$roleId]);
            $isSuper = $supers->contains($id);
            $canEnter = $isSuper || $held->contains(fn (Role $role): bool => $role->name === $panelRoleName);

            $permissions = match (true) {
                $isSuper => $allPermissions->all(),
                // Permissions are of no use to someone the admin panel doesn't let in.
                ! $canEnter => [],
                default => $held->flatMap(fn (Role $role): array => $role->permissions->pluck('name', 'id')->all())->all() + ($direct[$id] ?? []),
            };
            ksort($permissions);

            $key = $isSuper ? 'super' : implode(',', array_keys($permissions));
            $groups[$key] ??= [
                'permissions' => $permissions,
                'super' => $isSuper,
                'roles' => [],
                'people' => [],
            ];
            $groups[$key]['people'][] = [
                'user_id' => $id,
                'name' => $person['name'],
                'title' => $person['title'],
                'url' => $person['url'],
                'roles' => $held->reject(fn (Role $role): bool => $role->name === $panelRoleName)->map(fn (Role $role): string => $role->display_name)->values()->all(),
            ];
            foreach ($held as $role) {
                if ($role->name !== $panelRoleName && ($isSuper === ($role->name === Role::SUPER_ADMIN))) {
                    $groups[$key]['roles'][$role->display_name] = true;
                }
            }
        }

        $groups = array_values($groups);
        // Ids from 1, in a fixed order, so that the picture doesn't move between openings.
        usort($groups, fn (array $a, array $b): int => count($b['permissions']) <=> count($a['permissions']) ?: array_keys($a['permissions']) <=> array_keys($b['permissions']));
        $nodes = [];
        foreach ($groups as $index => $group) {
            $nodes[$index + 1] = ['permissions' => $group['permissions'], 'super' => $group['super']];
        }

        $relations = $this->order->relations($nodes);
        $label = fn (array $group): string => match (true) {
            $group['permissions'] === [] => 'بدون دسترسی در پنل',
            $group['roles'] === [] => 'مجوزهای مستقیم',
            default => implode(' + ', array_keys($group['roles'])),
        };
        $labelOf = fn (array $ids): array => array_map(fn (int $id): string => $label($groups[$id - 1]), $ids);
        $total = $allPermissions->count();

        $scene = [];
        foreach ($groups as $index => $group) {
            $id = $index + 1;
            $relation = $relations[$id];
            $count = count($group['permissions']);
            $headcount = count($group['people']);

            $scene[] = [
                'id' => $id,
                'label' => $label($group),
                'caption' => $label($group).' · '.$headcount.' نفر',
                'level' => $relation['level'],
                'permissions' => $count,
                'share' => $total > 0 ? (int) round($count / $total * 100) : 0,
                'holders' => $headcount,
                'super' => $group['super'],
                'tenant' => null,
                'parents' => $relation['parents'],
                'aboveIds' => $relation['above'],
                'belowIds' => $relation['below'],
                'peerIds' => $relation['peers'],
                'aboveLabels' => $labelOf($relation['parents']),
                'belowLabels' => $labelOf($relation['children']),
                'peerLabels' => [],
                'areas' => $this->order->areas($group['permissions']),
                'people' => collect($group['people'])->sortBy('name')->values()->all(),
                'url' => null,
            ];
        }

        usort($scene, fn (array $a, array $b): int => $b['level'] <=> $a['level'] ?: $b['permissions'] <=> $a['permissions']);

        return [
            'roles' => $scene,
            'levels' => $scene === [] ? 0 : $scene[0]['level'] + 1,
            'totalPermissions' => $total,
        ];
    }

    /**
     * Who holds which roles in this tenant.
     *
     * @param  list<int>  $userIds
     * @return Collection<int, list<int>>
     */
    private function assignments(array $userIds, Tenant $tenant): Collection
    {
        $morphKey = config('permission.column_names.model_morph_key');

        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn($morphKey, $userIds)
            ->where('tenant_id', $tenant->getKey())
            ->get([$morphKey, 'role_id'])
            ->groupBy($morphKey)
            ->map(fn (Collection $rows): array => $rows->pluck('role_id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * Permissions given to people directly in this tenant, keyed by id.
     *
     * @param  list<int>  $userIds
     * @return Collection<int, array<int, string>>
     */
    private function directPermissions(array $userIds, Tenant $tenant): Collection
    {
        $morphKey = config('permission.column_names.model_morph_key');
        $table = config('permission.table_names.model_has_permissions');
        $permissions = config('permission.table_names.permissions');

        return DB::table($table)
            ->join($permissions, "{$permissions}.id", '=', "{$table}.permission_id")
            ->where("{$table}.model_type", (new User)->getMorphClass())
            ->whereIn("{$table}.{$morphKey}", $userIds)
            ->where("{$table}.tenant_id", $tenant->getKey())
            ->get(["{$table}.{$morphKey} as user_id", "{$permissions}.id", "{$permissions}.name"])
            ->groupBy('user_id')
            ->map(fn (Collection $rows): array => $rows->pluck('name', 'id')->all());
    }

    /**
     * Holding the role in any tenant makes someone super admin everywhere (ADR 0007).
     *
     * @param  list<int>  $userIds
     * @return Collection<int, int>
     */
    private function superAdmins(array $userIds): Collection
    {
        $morphKey = config('permission.column_names.model_morph_key');

        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn($morphKey, $userIds)
            ->whereIn('role_id', Role::query()->where('name', Role::SUPER_ADMIN)->pluck('id'))
            ->pluck($morphKey)
            ->map(fn (mixed $id): int => (int) $id);
    }
}

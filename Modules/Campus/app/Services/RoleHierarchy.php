<?php

namespace Modules\Campus\Services;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * The roles' real order of power (ADR 0057), worked out by PowerOrder. Nothing is
 * entered, so it can't drift from what the roles can actually do. Roles are for
 * super admins, so this sees all of them.
 */
class RoleHierarchy
{
    public function __construct(private PowerOrder $order) {}

    /**
     * @return array{roles: list<array<string, mixed>>, levels: int, totalPermissions: int}
     */
    public function scene(): array
    {
        // Only what works in the admin panel: a role without permissions gives nothing
        // there, and panel_user only opens its door (ADR 0057).
        $roles = Role::query()
            ->with(['permissions:id,name', 'team:id,name'])
            ->where(fn (Builder $query) => $query->where('name', Role::SUPER_ADMIN)->orHas('permissions'))
            ->orderBy('id')
            ->get();
        $allPermissions = Permission::query()->pluck('name', 'id');
        $holders = $this->holders();

        $nodes = $roles->mapWithKeys(fn (Role $role): array => [$role->getKey() => [
            'permissions' => $role->name === Role::SUPER_ADMIN ? $allPermissions->all() : $role->permissions->pluck('name', 'id')->all(),
            'super' => $role->name === Role::SUPER_ADMIN,
        ]])->all();
        $relations = $this->order->relations($nodes);

        $byId = $roles->keyBy(fn (Role $role): int => $role->getKey());
        $labels = fn (array $roleIds): array => array_map(fn (int $id): string => $byId[$id]->display_name, $roleIds);
        $total = $allPermissions->count();

        $scene = $roles->map(function (Role $role) use ($nodes, $relations, $labels, $holders, $total): array {
            $id = $role->getKey();
            $count = count($nodes[$id]['permissions']);
            $relation = $relations[$id];

            return [
                'id' => $id,
                'label' => $role->display_name,
                'caption' => $role->display_name.' · '.$count,
                'level' => $relation['level'],
                'permissions' => $count,
                'share' => $total > 0 ? (int) round($count / $total * 100) : 0,
                'holders' => $holders[$id] ?? 0,
                'super' => $nodes[$id]['super'],
                'tenant' => $role->team?->name,
                'parents' => $relation['parents'],
                'aboveIds' => $relation['above'],
                'belowIds' => $relation['below'],
                'peerIds' => $relation['peers'],
                'aboveLabels' => $labels($relation['parents']),
                'belowLabels' => $labels($relation['children']),
                'peerLabels' => $labels($relation['peers']),
                'areas' => $this->order->areas($nodes[$id]['permissions']),
                'people' => [],
                'url' => RoleResource::getUrl('edit', ['record' => $role]),
            ];
        })->sortBy([['level', 'desc'], ['permissions', 'desc'], ['id', 'asc']])->values()->all();

        return [
            'roles' => $scene,
            'levels' => $scene === [] ? 0 : $scene[0]['level'] + 1,
            'totalPermissions' => $total,
        ];
    }

    /**
     * People holding each role, in any tenant; someone with it in two counts once.
     *
     * @return Collection<int, int>
     */
    private function holders(): Collection
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->selectRaw('role_id, count(distinct model_id) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->map(fn (mixed $total): int => (int) $total);
    }
}

<?php

namespace Modules\Campus\Services;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * The roles' real order of power (ADR 0057), from the rule that already governs
 * who may assign what (ADR 0003): a role is above another when it has all of the
 * other's permissions and more. Nothing is entered, so it can't drift from what
 * the roles can actually do. Roles are for super admins, so this sees all of them.
 */
class RoleHierarchy
{
    /**
     * @return array{roles: list<array<string, mixed>>, levels: int, totalPermissions: int}
     */
    public function scene(): array
    {
        $roles = Role::query()->with(['permissions:id,name', 'team:id,name'])->orderBy('id')->get();
        $allPermissions = Permission::query()->pluck('name', 'id');
        $holders = $this->holders();
        $areaLabels = $this->areaLabels();

        /** @var Collection<int, array<int, string>> $sets */
        $sets = $roles->mapWithKeys(fn (Role $role): array => [
            $role->getKey() => $role->name === Role::SUPER_ADMIN ? $allPermissions->all() : $role->permissions->pluck('name', 'id')->all(),
        ]);
        $isSuper = $roles->mapWithKeys(fn (Role $role): array => [$role->getKey() => $role->name === Role::SUPER_ADMIN]);
        $ids = $roles->modelKeys();

        $isBelow = function (int $lower, int $upper) use ($sets, $isSuper): bool {
            if ($lower === $upper || $isSuper[$lower]) {
                return false;
            }

            // A super admin passes every check, whatever others are granted.
            if ($isSuper[$upper]) {
                return true;
            }

            return count($sets[$lower]) < count($sets[$upper]) && array_diff_key($sets[$lower], $sets[$upper]) === [];
        };

        $above = collect($ids)->mapWithKeys(fn (int $id): array => [$id => array_values(array_filter($ids, fn (int $other): bool => $isBelow($id, $other)))]);
        $below = collect($ids)->mapWithKeys(fn (int $id): array => [$id => array_values(array_filter($ids, fn (int $other): bool => $isBelow($other, $id)))]);

        $levels = [];
        $levelOf = function (int $id) use (&$levels, &$levelOf, $below): int {
            return $levels[$id] ??= collect($below[$id])->map(fn (int $lower): int => $levelOf($lower) + 1)->max() ?? 0;
        };

        $byId = $roles->keyBy(fn (Role $role): int => $role->getKey());
        $labels = fn (array $roleIds): array => array_map(fn (int $id): string => $byId[$id]->display_name, $roleIds);
        $total = $allPermissions->count();

        $scene = $roles->map(function (Role $role) use ($sets, $isSuper, $above, $below, $ids, $levelOf, $labels, $holders, $areaLabels, $total): array {
            $id = $role->getKey();
            $count = count($sets[$id]);
            // Only the nearest: the rest follow through them.
            $parents = array_values(array_filter($above[$id], fn (int $upper): bool => array_intersect($above[$id], $below[$upper]) === []));
            $children = array_values(array_filter($below[$id], fn (int $lower): bool => array_intersect($below[$id], $above[$lower]) === []));
            $peers = array_values(array_filter($ids, fn (int $other): bool => $other !== $id
                && ! $isSuper[$other] && ! $isSuper[$id]
                && count($sets[$other]) === $count
                && array_diff_key($sets[$other], $sets[$id]) === []));

            return [
                'id' => $id,
                'label' => $role->display_name,
                'level' => $levelOf($id),
                'permissions' => $count,
                'share' => $total > 0 ? (int) round($count / $total * 100) : 0,
                'holders' => $holders[$id] ?? 0,
                'super' => $isSuper[$id],
                'system' => $role->isSystemRole(),
                'tenant' => $role->team?->name,
                'parents' => $parents,
                'aboveIds' => $above[$id],
                'belowIds' => $below[$id],
                'peerIds' => $peers,
                'aboveLabels' => $labels($parents),
                'belowLabels' => $labels($children),
                'peerLabels' => $labels($peers),
                'areas' => $this->areas($sets[$id], $areaLabels),
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

    /**
     * What a permission's subject («Create:Classroom») is called on the screens.
     *
     * @return array<string, string>
     */
    private function areaLabels(): array
    {
        return collect(Filament::getPanel('admin')->getResources())
            ->mapWithKeys(fn (string $resource): array => [class_basename($resource::getModel()) => $resource::getPluralModelLabel()])
            ->all();
    }

    /**
     * @param  array<int, string>  $permissions
     * @param  array<string, string>  $areaLabels
     * @return list<array{label: string, count: int}>
     */
    private function areas(array $permissions, array $areaLabels): array
    {
        return collect($permissions)
            ->countBy(fn (string $name): string => str_contains($name, ':') ? explode(':', $name, 2)[1] : $name)
            ->map(fn (int $count, string $subject): array => ['label' => $areaLabels[$subject] ?? $subject, 'count' => $count])
            ->sortByDesc('count')
            ->values()
            ->all();
    }
}

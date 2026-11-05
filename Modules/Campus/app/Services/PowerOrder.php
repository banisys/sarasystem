<?php

namespace Modules\Campus\Services;

use Filament\Facades\Filament;

/**
 * The order of power among sets of permissions (ADR 0057, ADR 0058), by the rule
 * that governs who may assign what (ADR 0003): one is above another when it has
 * all of the other's permissions and more. A super admin passes every check, so
 * it is above everything, even a set with all the permissions.
 */
class PowerOrder
{
    /**
     * @param  array<int, array{permissions: array<int, string>, super: bool}>  $nodes  permissions keyed by id
     * @return array<int, array{level: int, above: list<int>, below: list<int>, parents: list<int>, children: list<int>, peers: list<int>}>
     */
    public function relations(array $nodes): array
    {
        $ids = array_keys($nodes);

        $isBelow = function (int $lower, int $upper) use ($nodes): bool {
            if ($lower === $upper || $nodes[$lower]['super']) {
                return false;
            }

            if ($nodes[$upper]['super']) {
                return true;
            }

            return count($nodes[$lower]['permissions']) < count($nodes[$upper]['permissions'])
                && array_diff_key($nodes[$lower]['permissions'], $nodes[$upper]['permissions']) === [];
        };

        $above = [];
        $below = [];
        foreach ($ids as $id) {
            $above[$id] = array_values(array_filter($ids, fn (int $other): bool => $isBelow($id, $other)));
            $below[$id] = array_values(array_filter($ids, fn (int $other): bool => $isBelow($other, $id)));
        }

        $levels = [];
        $levelOf = function (int $id) use (&$levels, &$levelOf, $below): int {
            return $levels[$id] ??= collect($below[$id])->map(fn (int $lower): int => $levelOf($lower) + 1)->max() ?? 0;
        };

        $relations = [];
        foreach ($ids as $id) {
            $relations[$id] = [
                'level' => $levelOf($id),
                'above' => $above[$id],
                'below' => $below[$id],
                // Only the nearest: the rest follow through them.
                'parents' => array_values(array_filter($above[$id], fn (int $upper): bool => array_intersect($above[$id], $below[$upper]) === [])),
                'children' => array_values(array_filter($below[$id], fn (int $lower): bool => array_intersect($below[$id], $above[$lower]) === [])),
                'peers' => array_values(array_filter($ids, fn (int $other): bool => $other !== $id
                    && ! $nodes[$other]['super'] && ! $nodes[$id]['super']
                    && count($nodes[$other]['permissions']) === count($nodes[$id]['permissions'])
                    && array_diff_key($nodes[$other]['permissions'], $nodes[$id]['permissions']) === [])),
            ];
        }

        return $relations;
    }

    /**
     * How many of the permissions are in each area, named as on the screens: the
     * subject of «Create:Classroom» is the plural label of its resource.
     *
     * @param  array<int, string>  $permissions
     * @return list<array{label: string, count: int}>
     */
    public function areas(array $permissions): array
    {
        $labels = $this->areaLabels();

        return collect($permissions)
            ->countBy(fn (string $name): string => str_contains($name, ':') ? explode(':', $name, 2)[1] : $name)
            ->map(fn (int $count, string $subject): array => ['label' => $labels[$subject] ?? $subject, 'count' => $count])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function areaLabels(): array
    {
        return once(fn (): array => collect(Filament::getPanel('admin')->getResources())
            ->mapWithKeys(fn (string $resource): array => [class_basename($resource::getModel()) => $resource::getPluralModelLabel()])
            ->all());
    }
}

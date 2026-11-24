<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A snapshot of category records already scoped and ordered by the caller.
 *
 * @template TCategory of Model
 */
abstract class CategoryTree
{
    /** @var array<int, list<TCategory>> */
    private array $children = [];

    /** @var array<int, TCategory> */
    private array $categories = [];

    /** @var array<int, list<string>> */
    private array $paths = [];

    /** @var array<int, int|null> */
    private array $parents = [];

    /** @param Collection<int, TCategory> $categories */
    protected function __construct(Collection $categories)
    {
        foreach ($categories as $category) {
            $this->children[$category->parent_id ?? 0][] = $category;
            $this->categories[$category->id] = $category;
            $this->parents[$category->id] = $category->parent_id;
        }

        $this->buildPaths();
    }

    /**
     * @param  array<int, true>|null  $visibleIds
     * @return list<TCategory>
     */
    public function childrenOf(?int $parentId, ?array $visibleIds = null): array
    {
        $children = $this->children[$parentId ?? 0] ?? [];

        return $visibleIds === null ? $children : array_values(array_filter(
            $children, fn (Model $category): bool => isset($visibleIds[$category->id]),
        ));
    }

    /** @return array<int, true>|null */
    public function matchingIds(string $search): ?array
    {
        if ($search === '') {
            return null;
        }

        $visible = [];
        foreach ($this->paths as $id => $path) {
            if (mb_stripos(implode(' / ', $path).$this->searchSuffix($this->categories[$id]), $search) === false) {
                continue;
            }

            while ($id !== null && ! isset($visible[$id])) {
                $visible[$id] = true;
                $id = $this->parents[$id];
            }
        }

        return $visible;
    }

    /** @param TCategory $category */
    protected function searchSuffix(Model $category): string
    {
        return '';
    }

    /** @return list<string>|null */
    public function path(?int $id): ?array
    {
        return $this->paths[$id] ?? null;
    }

    /** @return list<array{id: int, label: string, children: array}> */
    public function nodes(?int $excludingBranch = null, ?int $parentId = null): array
    {
        $nodes = [];
        foreach ($this->childrenOf($parentId) as $category) {
            if ($category->id === $excludingBranch) {
                continue;
            }

            $nodes[] = [
                'id' => $category->id,
                'label' => $category->title,
                'children' => $this->nodes($excludingBranch, $category->id),
            ];
        }

        return $nodes;
    }

    /** @return list<int> */
    public function branchIds(int $id): array
    {
        if (! isset($this->paths[$id])) {
            return [];
        }

        $ids = [$id];
        for ($index = 0; $index < count($ids); $index++) {
            foreach ($this->children[$ids[$index]] ?? [] as $child) {
                $ids[] = $child->id;
            }
        }

        return $ids;
    }

    /** @param list<string> $ancestors */
    private function buildPaths(int $parentId = 0, array $ancestors = []): void
    {
        foreach ($this->children[$parentId] ?? [] as $child) {
            $path = [...$ancestors, $child->title];
            $this->paths[$child->id] = $path;
            $this->buildPaths($child->id, $path);
        }
    }
}

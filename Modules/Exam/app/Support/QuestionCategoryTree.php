<?php

namespace Modules\Exam\Support;

use Illuminate\Database\Eloquent\Collection;
use Modules\Exam\Models\QuestionCategory;

/** A snapshot of the current tenant's category tree for admin display and filtering. */
class QuestionCategoryTree
{
    /** @var array<int, list<QuestionCategory>> */
    private array $children = [];

    /** @var array<int, string> */
    private array $paths = [];

    /** @var array<int, int|null> */
    private array $parents = [];

    /** @param Collection<int, QuestionCategory> $categories */
    private function __construct(Collection $categories)
    {
        foreach ($categories as $category) {
            $this->children[$category->parent_id ?? 0][] = $category;
            $this->parents[$category->id] = $category->parent_id;
        }

        $this->buildPaths();
    }

    public static function current(bool $withQuestionCounts = false): self
    {
        $query = QuestionCategory::query()->select(['id', 'parent_id', 'title'])->orderBy('title')->orderBy('id');

        return new self($query->when($withQuestionCounts, fn ($query) => $query->withCount('questions'))->get());
    }

    /**
     * @param  array<int, true>|null  $visibleIds
     * @return list<QuestionCategory>
     */
    public function childrenOf(?int $parentId, ?array $visibleIds = null): array
    {
        $children = $this->children[$parentId ?? 0] ?? [];

        return $visibleIds === null ? $children : array_values(array_filter(
            $children, fn (QuestionCategory $category): bool => isset($visibleIds[$category->id]),
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
            if (mb_stripos($path, $search) === false) {
                continue;
            }

            while ($id !== null && ! isset($visible[$id])) {
                $visible[$id] = true;
                $id = $this->parents[$id];
            }
        }

        return $visible;
    }

    /** @return array<int, string> */
    public function options(?int $excludingBranch = null): array
    {
        return $excludingBranch === null
            ? $this->paths
            : array_diff_key($this->paths, array_flip($this->branchIds($excludingBranch)));
    }

    public function label(?int $id): ?string
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

    private function buildPaths(int $parentId = 0, string $prefix = ''): void
    {
        foreach ($this->children[$parentId] ?? [] as $child) {
            $path = $prefix.$child->title;
            $this->paths[$child->id] = $path;
            $this->buildPaths($child->id, $path.' / ');
        }
    }
}

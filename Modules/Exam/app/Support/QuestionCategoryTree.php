<?php

namespace Modules\Exam\Support;

use Illuminate\Database\Eloquent\Collection;
use Modules\Exam\Models\QuestionCategory;

/** A snapshot of the current tenant's category tree for admin labels and filtering. */
class QuestionCategoryTree
{
    /** @var array<int, list<QuestionCategory>> */
    private array $children = [];

    /** @var array<int, string> */
    private array $paths = [];

    /** @param Collection<int, QuestionCategory> $categories */
    private function __construct(Collection $categories)
    {
        foreach ($categories as $category) {
            $this->children[$category->parent_id ?? 0][] = $category;
        }

        $this->buildPaths();
    }

    public static function current(): self
    {
        return new self(QuestionCategory::query()->orderBy('title')->orderBy('id')->get(['id', 'parent_id', 'title']));
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

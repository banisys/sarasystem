<?php

namespace Modules\Course\Support;

use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Course\Models\CourseCategory;

/** @extends CategoryTree<CourseCategory> */
class CourseCategoryTree extends CategoryTree
{
    public static function current(bool $withCourseCounts = false, bool $catalog = false): self
    {
        $query = CourseCategory::query()->select(['id', 'parent_id', 'title', 'alias', 'is_active'])
            ->orderBy('position')->orderBy('title')->orderBy('id');

        if ($catalog) {
            $query->where('is_active', true)->withCount([
                'courses' => fn (Builder $courses): Builder => $courses->published(),
            ]);
        } elseif ($withCourseCounts) {
            $query->withCount('courses');
        }

        return new self($query->get());
    }

    protected function searchSuffix(Model $category): string
    {
        return ' '.$category->alias;
    }

    /** @return list<array{id: int, title: string}> */
    public function catalogOptions(?int $parentId = null): array
    {
        $options = [];

        foreach ($this->childrenOf($parentId) as $category) {
            $children = $this->catalogOptions($category->id);

            if ($category->courses_count || $children !== []) {
                $options[] = ['id' => $category->id, 'title' => implode(' / ', $this->path($category->id))];
                $options = [...$options, ...$children];
            }
        }

        return $options;
    }
}

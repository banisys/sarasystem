<?php

namespace Modules\Exam\Support;

use App\Support\CategoryTree;
use Modules\Exam\Models\QuestionCategory;

/** @extends CategoryTree<QuestionCategory> */
class QuestionCategoryTree extends CategoryTree
{
    public static function current(bool $withQuestionCounts = false): self
    {
        $query = QuestionCategory::query()->select(['id', 'parent_id', 'title'])->orderBy('title')->orderBy('id');

        return new self($query->when($withQuestionCounts, fn ($query) => $query->withCount('questions'))->get());
    }
}

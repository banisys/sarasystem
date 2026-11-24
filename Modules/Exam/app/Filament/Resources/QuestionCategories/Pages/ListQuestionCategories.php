<?php

namespace Modules\Exam\Filament\Resources\QuestionCategories\Pages;

use App\Filament\Resources\Pages\ListCategoryTree;
use Illuminate\Database\Eloquent\Model;
use Modules\Exam\Filament\Resources\QuestionCategories\QuestionCategoryResource;
use Modules\Exam\Support\QuestionCategoryTree;

class ListQuestionCategories extends ListCategoryTree
{
    protected static string $resource = QuestionCategoryResource::class;

    protected function categoryTree(): QuestionCategoryTree
    {
        return QuestionCategoryTree::current(withQuestionCounts: true);
    }

    protected function nodeCaption(Model $record): string
    {
        return $record->questions_count.' سؤال';
    }

    protected function linkedItemLabel(): string
    {
        return 'سؤال';
    }
}

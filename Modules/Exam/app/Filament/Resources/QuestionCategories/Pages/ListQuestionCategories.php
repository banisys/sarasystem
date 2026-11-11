<?php

namespace Modules\Exam\Filament\Resources\QuestionCategories\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Exam\Filament\Resources\QuestionCategories\QuestionCategoryResource;

class ListQuestionCategories extends ListRecords
{
    protected static string $resource = QuestionCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(QuestionCategoryResource::saveCategory(...)),
        ];
    }
}

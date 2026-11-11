<?php

namespace Modules\Exam\Filament\Resources\Questions\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Exam\Filament\Resources\Questions\QuestionResource;

class ListQuestions extends ListRecords
{
    protected static string $resource = QuestionResource::class;

    public function mount(): void
    {
        $previousCategory = data_get($this->tableFilters, 'category.value');
        if (is_int($previousCategory) || is_string($previousCategory)) {
            $this->tableFilters['category']['values'] ??= [$previousCategory];
            unset($this->tableFilters['category']['value']);
        }

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

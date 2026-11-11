<?php

namespace Modules\Exam\Filament\Resources\QuestionTags\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Exam\Filament\Resources\QuestionTags\QuestionTagResource;

class ManageQuestionTags extends ManageRecords
{
    protected static string $resource = QuestionTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('افزودن برچسب'),
        ];
    }
}

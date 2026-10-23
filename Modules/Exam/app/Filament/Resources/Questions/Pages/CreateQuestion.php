<?php

namespace Modules\Exam\Filament\Resources\Questions\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Exam\Filament\Resources\Questions\QuestionResource;

class CreateQuestion extends CreateRecord
{
    protected static string $resource = QuestionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

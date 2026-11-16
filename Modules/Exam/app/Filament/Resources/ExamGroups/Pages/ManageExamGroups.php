<?php

namespace Modules\Exam\Filament\Resources\ExamGroups\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Exam\Filament\Resources\ExamGroups\ExamGroupResource;

class ManageExamGroups extends ManageRecords
{
    protected static string $resource = ExamGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('افزودن گروه آزمون'),
        ];
    }
}

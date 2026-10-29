<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;

class ListClassrooms extends ListRecords
{
    protected static string $resource = ClassroomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('3xl'),
        ];
    }
}

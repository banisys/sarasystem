<?php

namespace Modules\School\Filament\Resources\Majors\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\School\Filament\Resources\Majors\MajorResource;

class ListMajors extends ListRecords
{
    protected static string $resource = MajorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

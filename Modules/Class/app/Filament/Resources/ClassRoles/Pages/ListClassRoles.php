<?php

namespace Modules\Class\Filament\Resources\ClassRoles\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Class\Filament\Resources\ClassRoles\ClassRoleResource;

class ListClassRoles extends ListRecords
{
    protected static string $resource = ClassRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

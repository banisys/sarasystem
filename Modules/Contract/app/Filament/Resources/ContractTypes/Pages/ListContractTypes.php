<?php

namespace Modules\Contract\Filament\Resources\ContractTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Contract\Filament\Resources\ContractTypes\ContractTypeResource;

class ListContractTypes extends ListRecords
{
    protected static string $resource = ContractTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->modalWidth('5xl'),
        ];
    }
}

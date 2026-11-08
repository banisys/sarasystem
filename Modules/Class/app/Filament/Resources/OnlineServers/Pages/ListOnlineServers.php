<?php

namespace Modules\Class\Filament\Resources\OnlineServers\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Class\Filament\Resources\OnlineServers\OnlineServerResource;

class ListOnlineServers extends ListRecords
{
    protected static string $resource = OnlineServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

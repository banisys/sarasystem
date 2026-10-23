<?php

namespace Modules\Site\Filament\Resources\SiteMenus\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Site\Filament\Resources\SiteMenus\MenuResource;
use Modules\Site\Models\Menu;

class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // A new menu is empty: go on to its items.
                ->successRedirectUrl(fn (Menu $record): string => MenuResource::getUrl('items', ['record' => $record])),
        ];
    }
}

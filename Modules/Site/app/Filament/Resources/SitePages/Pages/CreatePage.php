<?php

namespace Modules\Site\Filament\Resources\SitePages\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Site\Filament\Resources\SitePages\PageResource;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /**
     * The page may belong to another tenant than the panel's: open it there.
     */
    protected function getRedirectUrl(): string
    {
        return PageResource::getUrl('edit', ['record' => $this->getRecord()], tenant: $this->getRecord()->tenant);
    }
}

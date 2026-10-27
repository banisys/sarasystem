<?php

namespace Modules\Document\Filament\Resources\DocumentTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Document\Filament\Resources\DocumentTypes\DocumentTypeResource;
use Modules\Document\Filament\Resources\DocumentTypes\Schemas\DocumentTypeForm;

class ListDocumentTypes extends ListRecords
{
    protected static string $resource = DocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data) => DocumentTypeForm::save(null, $data)),
        ];
    }
}

<?php

namespace Modules\Student\Filament\Resources\Preregistrations\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Student\Filament\Resources\Preregistrations\PreregistrationResource;

class ListPreregistrations extends ListRecords
{
    protected static string $resource = PreregistrationResource::class;
}

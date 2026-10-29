<?php

namespace Modules\School\Filament\Resources\ScorePeriods\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\School\Filament\Resources\ScorePeriods\ScorePeriodResource;

class ListScorePeriods extends ListRecords
{
    protected static string $resource = ScorePeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

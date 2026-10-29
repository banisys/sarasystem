<?php

namespace Modules\Class\Filament\Resources\TeachingPlans\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Class\Filament\Resources\TeachingPlans\TeachingPlanResource;

class ListTeachingPlans extends ListRecords
{
    protected static string $resource = TeachingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('4xl'),
        ];
    }
}

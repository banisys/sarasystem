<?php

namespace Modules\Employee\Filament\Resources\JobPositions\Pages;

use App\Filament\CustomFields\CustomizeFormAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Employee\Filament\Resources\JobPositions\JobPositionResource;
use Modules\Employee\Forms\JobApplicationForm;

class ListJobPositions extends ListRecords
{
    protected static string $resource = JobPositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The form's setup for all positions (ADR 0017).
            CustomizeFormAction::make('customizeForm', new JobApplicationForm)
                ->authorize(fn (): bool => auth()->user()?->can('Update:JobPosition') ?? false),
            CreateAction::make(),
        ];
    }
}

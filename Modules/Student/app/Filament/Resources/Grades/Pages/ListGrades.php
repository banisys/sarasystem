<?php

namespace Modules\Student\Filament\Resources\Grades\Pages;

use App\Filament\CustomFields\CustomizeFormAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Student\Filament\Resources\Grades\GradeResource;
use Modules\Student\Forms\PreregistrationForm;

class ListGrades extends ListRecords
{
    protected static string $resource = GradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The form's setup for all stages (ADR 0017).
            CustomizeFormAction::make('customizeForm', new PreregistrationForm)
                ->authorize(fn (): bool => auth()->user()?->can('Update:Grade') ?? false),
            CreateAction::make(),
        ];
    }
}

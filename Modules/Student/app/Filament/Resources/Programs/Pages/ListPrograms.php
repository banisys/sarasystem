<?php

namespace Modules\Student\Filament\Resources\Programs\Pages;

use App\Filament\CustomFields\CustomizeFormAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Student\Filament\Resources\Programs\ProgramResource;
use Modules\Student\Forms\PreregistrationForm;

class ListPrograms extends ListRecords
{
    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The form's setup for all programs (ADR 0017).
            CustomizeFormAction::make('customizeForm', new PreregistrationForm)
                ->authorize(fn (): bool => auth()->user()?->can('Update:Program') ?? false),
            CreateAction::make(),
        ];
    }
}

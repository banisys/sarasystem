<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Support\ClassroomExtensions;

class ViewClassroom extends ViewRecord
{
    protected static string $resource = ClassroomResource::class;

    /**
     * The details are the first tab, before the members.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'اطلاعات';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Actions other modules add, e.g. the score book (ADR 0032).
            ...ClassroomExtensions::components('actions'),
            EditAction::make()
                ->modalWidth('3xl'),
        ];
    }
}

<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use BackedEnum;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
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

    public function getContentTabIcon(): string|BackedEnum|Htmlable|null
    {
        return Heroicon::OutlinedInformationCircle;
    }

    public function getRelationManagersContentComponent(): Component
    {
        return parent::getRelationManagersContentComponent()
            ->extraAttributes(['class' => 'classroom-tabs']);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Actions other modules add, e.g. the score book (ADR 0032).
            ...ClassroomExtensions::components('actions'),
        ];
    }
}

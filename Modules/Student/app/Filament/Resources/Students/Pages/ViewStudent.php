<?php

namespace Modules\Student\Filament\Resources\Students\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Student\Filament\Resources\Students\StudentResource;
use Modules\Student\Models\Student;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    public function getTitle(): string
    {
        /** @var Student $student */
        $student = $this->getRecord();

        return $student->user?->name ?? config('sarasystem.learner_label');
    }

    /**
     * The details are the first tab, before the tabs other modules add.
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
            EditAction::make(),
        ];
    }
}

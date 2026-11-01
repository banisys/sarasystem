<?php

namespace App\Filament\Resources\AllWorkflowCases\Pages;

use App\Filament\Resources\AllWorkflowCases\AllWorkflowCasesResource;
use App\Models\WorkflowCase;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * One case, read only: what it is about, where it is and its history.
 * Acting on it stays in the assignee's cartable.
 */
class ViewAllWorkflowCase extends ViewRecord
{
    protected static string $resource = AllWorkflowCasesResource::class;

    public function getTitle(): string
    {
        return $this->case()->title();
    }

    public function getSubheading(): ?string
    {
        return $this->case()->workflow_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('flowchart')
                ->label('فلوچارت فرایند')
                ->icon(Heroicon::OutlinedShare)
                ->color('gray')
                ->url(fn (): string => AllWorkflowCasesResource::getUrl('flowchart', ['record' => $this->case()])),
        ];
    }

    private function case(): WorkflowCase
    {
        /** @var WorkflowCase */
        return $this->getRecord();
    }
}

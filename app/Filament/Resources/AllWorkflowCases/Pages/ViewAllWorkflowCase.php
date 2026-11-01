<?php

namespace App\Filament\Resources\AllWorkflowCases\Pages;

use App\Filament\Resources\AllWorkflowCases\AllWorkflowCasesResource;
use App\Models\WorkflowCase;
use Filament\Resources\Pages\ViewRecord;

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

    private function case(): WorkflowCase
    {
        /** @var WorkflowCase */
        return $this->getRecord();
    }
}

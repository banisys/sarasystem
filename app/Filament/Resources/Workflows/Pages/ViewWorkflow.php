<?php

namespace App\Filament\Resources\Workflows\Pages;

use App\Filament\Resources\Workflows\WorkflowResource;
use App\Filament\Workflow\WorkflowFlowchart;
use App\Models\Workflow;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * A workflow drawn as a flowchart: start, each step with its assignee and
 * allowed actions, the reject exits and the end (ADR 0016).
 */
class ViewWorkflow extends ViewRecord
{
    protected static string $resource = WorkflowResource::class;

    protected string $view = 'filament.resources.workflows.view-workflow';

    public function getTitle(): string
    {
        return "فرایند «{$this->workflow()->name}»";
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * @return array{subjectLabel: string, completionSummary: ?string, steps: list<array<string, mixed>>, completed: bool}
     */
    public function chart(): array
    {
        return WorkflowFlowchart::ofWorkflow($this->workflow());
    }

    private function workflow(): Workflow
    {
        /** @var Workflow */
        return $this->getRecord();
    }
}

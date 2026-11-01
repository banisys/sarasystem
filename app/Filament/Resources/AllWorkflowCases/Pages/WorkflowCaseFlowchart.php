<?php

namespace App\Filament\Resources\AllWorkflowCases\Pages;

use App\Filament\Resources\AllWorkflowCases\AllWorkflowCasesResource;
use App\Filament\Workflow\WorkflowFlowchart;
use App\Models\WorkflowCase;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * «فلوچارت فرایند» of one case: the steps it copied when it started, where
 * it is, and under each step who did what, when and their notes (ADR 0043).
 */
class WorkflowCaseFlowchart extends ViewRecord
{
    protected static string $resource = AllWorkflowCasesResource::class;

    protected string $view = 'filament.resources.all-workflow-cases.flowchart';

    public function getTitle(): string
    {
        return "فرایند «{$this->case()->workflow_name}»";
    }

    public function getSubheading(): ?string
    {
        return $this->case()->title().' · '.$this->case()->status->getLabel();
    }

    public function getBreadcrumb(): string
    {
        return 'فلوچارت فرایند';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('case')
                ->label('مشاهده‌ی پرونده')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (): string => AllWorkflowCasesResource::getUrl('view', ['record' => $this->case()])),
        ];
    }

    /**
     * @return array{subjectLabel: string, completionSummary: ?string, steps: list<array<string, mixed>>, completed: bool, started: list<array<string, mixed>>}
     */
    public function chart(): array
    {
        return WorkflowFlowchart::ofCase($this->case());
    }

    private function case(): WorkflowCase
    {
        /** @var WorkflowCase */
        return $this->getRecord();
    }
}

<?php

namespace App\Filament\Resources\Workflows\Pages;

use App\Filament\Resources\Workflows\WorkflowResource;
use App\Models\Workflow;
use App\Workflow\AssigneeType;
use App\Workflow\CaseStatus;
use App\Workflow\StepAction;
use App\Workflow\WorkflowStep;
use App\Workflow\WorkflowSubjects;
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
     * @return list<array{number: int, name: string, assigneeIsUser: bool, assignee: string, actions: list<array{label: string, color: string}>, rejects: bool, returns: bool, openCases: int}>
     */
    public function steps(): array
    {
        $openCases = $this->workflow()->cases()
            ->where('status', CaseStatus::InProgress)
            ->selectRaw('current_step, count(*) as total')
            ->groupBy('current_step')
            ->pluck('total', 'current_step');

        return collect($this->workflow()->stepList())
            ->map(fn (WorkflowStep $step, int $index): array => [
                'number' => $index + 1,
                'name' => $step->name,
                'assigneeIsUser' => $step->assigneeType === AssigneeType::User,
                'assignee' => $step->assigneeLabel(),
                'actions' => [
                    ['label' => 'تأیید', 'color' => 'success'],
                    ...collect($step->actions)->map(fn (StepAction $action): array => [
                        'label' => $action->getLabel(),
                        'color' => match ($action) {
                            StepAction::Reject => 'danger',
                            StepAction::Return => 'warning',
                            StepAction::Edit => 'info',
                        },
                    ])->all(),
                ],
                'rejects' => $step->allows(StepAction::Reject),
                'returns' => $step->allows(StepAction::Return) && $index > 0,
                'openCases' => (int) ($openCases[$index] ?? 0),
            ])
            ->all();
    }

    public function subjectLabel(): string
    {
        return WorkflowSubjects::find($this->workflow()->subject_type)?->label() ?? 'پرونده';
    }

    public function completionSummary(): ?string
    {
        return WorkflowSubjects::find($this->workflow()->subject_type)?->completionSummary();
    }

    private function workflow(): Workflow
    {
        /** @var Workflow */
        return $this->getRecord();
    }
}

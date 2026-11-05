<?php

namespace Modules\Campus\Services;

use App\Filament\Workflow\WorkflowFlowchart;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Workflow\AssigneeType;
use App\Workflow\StepAction;
use App\Workflow\WorkflowStep;

/**
 * What the 3D view of a workflow draws (ADR 0064): the same steps, assignees,
 * rules and open cases as its flowchart, with a lane per tenant and each step's
 * outcomes in words.
 */
class WorkflowScene
{
    /**
     * The lanes' colours, in the order the tenants first appear in the workflow.
     */
    private const array LANE_COLORS = ['#6366f1', '#0ea5e9', '#14b8a6', '#ec4899', '#f97316', '#84cc16'];

    /**
     * @return array{subjectLabel: string, finish: string, openCases: int, lanes: list<array{index: int, name: string, color: string, steps: list<string>}>, steps: list<array<string, mixed>>}
     */
    public function scene(Workflow $workflow): array
    {
        $chart = WorkflowFlowchart::ofWorkflow($workflow);
        $stepList = $workflow->stepList();
        $tenantNames = Tenant::query()->whereKey(array_map(fn (WorkflowStep $step): int => $step->tenantId, $stepList))->pluck('name', 'id');
        $finish = $chart['completionSummary'] ?? 'تأیید نهایی';
        $laneOf = [];

        foreach ($stepList as $step) {
            $laneOf[$step->tenantId] ??= count($laneOf);
        }

        $steps = [];

        foreach ($stepList as $index => $step) {
            $next = $stepList[$index + 1] ?? null;
            $tenant = $this->tenantName($tenantNames->all(), $step->tenantId);

            $steps[] = [
                'kind' => 'step',
                'index' => $index,
                'number' => $index + 1,
                'name' => $step->name,
                'assignee' => $chart['steps'][$index]['assignee'],
                'isPerson' => $step->assigneeType === AssigneeType::User,
                'lane' => $laneOf[$step->tenantId],
                'tenant' => $tenant,
                'rejects' => $step->allows(StepAction::Reject),
                'returns' => $chart['steps'][$index]['returns'],
                'edits' => $step->allows(StepAction::Edit),
                'notes' => $chart['steps'][$index]['notes'],
                'openCases' => $chart['steps'][$index]['openCases'],
                'outcomes' => $this->outcomes($step, $next, $index, $finish, $tenantNames->all()),
            ];
        }

        $lanes = [];

        foreach ($laneOf as $tenantId => $lane) {
            $lanes[] = [
                'index' => $lane,
                'name' => $this->tenantName($tenantNames->all(), $tenantId),
                'color' => self::LANE_COLORS[$lane % count(self::LANE_COLORS)],
                'steps' => collect($steps)->where('lane', $lane)->map(fn (array $step): string => "{$step['number']}. {$step['name']}")->values()->all(),
            ];
        }

        return [
            'subjectLabel' => $chart['subjectLabel'],
            'finish' => $finish,
            'openCases' => collect($steps)->sum('openCases'),
            'lanes' => $lanes,
            'steps' => $steps,
        ];
    }

    /**
     * What can happen to a case at this step.
     *
     * @param  array<int, string>  $tenantNames
     * @return list<array{tone: string, text: string}>
     */
    private function outcomes(WorkflowStep $step, ?WorkflowStep $next, int $index, string $finish, array $tenantNames): array
    {
        if ($next === null) {
            $approved = "با تأیید، فرایند تمام می‌شود: {$finish}";
        } elseif ($next->tenantId !== $step->tenantId) {
            $approved = "با تأیید، پرونده به «{$next->name}» در {$this->tenantName($tenantNames, $next->tenantId)} می‌رود";
        } else {
            $approved = "با تأیید، پرونده به «{$next->name}» می‌رود";
        }

        return [
            ['tone' => 'approve', 'text' => $approved],
            ...($step->allows(StepAction::Reject) ? [['tone' => 'reject', 'text' => 'با رد، پرونده همین‌جا بسته می‌شود']] : []),
            ...($step->allows(StepAction::Return) && $index > 0 ? [['tone' => 'return', 'text' => 'می‌تواند پرونده را به هر یک از مرحله‌های قبل برگرداند']] : []),
            ...($step->allows(StepAction::Edit) ? [['tone' => 'edit', 'text' => 'می‌تواند اطلاعات پرونده را ویرایش کند']] : []),
        ];
    }

    /**
     * @param  array<int, string>  $tenantNames
     */
    private function tenantName(array $tenantNames, int $tenantId): string
    {
        return $tenantNames[$tenantId] ?? '؟';
    }
}

<?php

namespace App\Filament\Workflow;

use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Models\WorkflowCaseAction;
use App\Workflow\AssigneeType;
use App\Workflow\CaseActionType;
use App\Workflow\CaseStatus;
use App\Workflow\StepAction;
use App\Workflow\StepExtension;
use App\Workflow\WorkflowStep;
use App\Workflow\WorkflowStepExtensions;
use App\Workflow\WorkflowSubjects;
use Ariaieboy\Jalali\Jalali;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * The data of a workflow flowchart (`filament.workflow.flowchart`): of a
 * workflow with its open cases per step («نمایش فرایند»), or of one case,
 * drawn from the steps it copied, with where it is (ADR 0016, ADR 0043).
 */
class WorkflowFlowchart
{
    public const string DONE = 'done';

    public const string CURRENT = 'current';

    public const string REJECTED = 'rejected';

    /**
     * @return array{subjectLabel: string, completionSummary: ?string, steps: list<array{number: int, name: string, assigneeIsUser: bool, assignee: string, actions: list<array{label: string, color: string}>, rejects: bool, returns: bool, notes: list<string>, openCases: int, state: ?string, history: list<array<string, mixed>>, waiting: ?string}>, completed: bool, started: list<array<string, mixed>>}
     */
    public static function ofWorkflow(Workflow $workflow): array
    {
        $openCases = $workflow->cases()
            ->where('status', CaseStatus::InProgress)
            ->selectRaw('current_step, count(*) as total')
            ->groupBy('current_step')
            ->pluck('total', 'current_step');

        return self::chart(
            $workflow->stepList(),
            $workflow->subject_type,
            fn (int $index): int => (int) ($openCases[$index] ?? 0),
            fn (): ?string => null,
            completed: false,
        );
    }

    /**
     * A case's flowchart, drawn from the steps it copied, with where it is
     * and what was done in each step: who approved,
     * returned, edited or rejected it, when, and their notes.
     *
     * @return array{subjectLabel: string, completionSummary: ?string, steps: list<array<string, mixed>>, completed: bool, started: list<array<string, mixed>>}
     */
    public static function ofCase(WorkflowCase $case): array
    {
        $case->loadMissing(['actions' => fn ($query) => $query->orderBy('id'), 'actions.user', 'claimedBy']);
        $history = $case->actions->groupBy(fn (WorkflowCaseAction $action): string => $action->from_step === null ? 'start' : (string) $action->from_step);
        $chart = self::stepsOf($case);

        $chart['started'] = self::entries($history->get('start', collect())->values()->all(), $case);
        foreach ($chart['steps'] as $index => $step) {
            $chart['steps'][$index]['history'] = self::entries($history->get((string) $index, collect())->values()->all(), $case);
            if ($step['state'] === self::CURRENT) {
                $since = $case->step_started_at === null ? '' : ' از '.self::jalali($case->step_started_at);
                $chart['steps'][$index]['waiting'] = $case->claimedBy !== null
                    ? "در دست {$case->claimedBy->name}{$since}"
                    : 'در انتظار '.$case->assigneeName().$since;
            }
        }

        return $chart;
    }

    /**
     * @param  list<WorkflowCaseAction>  $actions
     * @return list<array{label: string, color: string, user: string, at: string, comment: ?string, changes: list<string>, target: ?string}>
     */
    private static function entries(array $actions, WorkflowCase $case): array
    {
        return array_map(fn (WorkflowCaseAction $action): array => [
            'label' => $action->type->getLabel(),
            'color' => $action->type->getColor(),
            'user' => $action->user?->name ?? 'سامانه / متقاضی',
            'at' => self::jalali($action->created_at),
            'comment' => $action->comment,
            'changes' => collect($action->changes ?? [])
                ->map(fn (array $change): string => "{$change['label']}: «".($change['old'] ?? '—').'» ← «'.($change['new'] ?? '—').'»')
                ->values()
                ->all(),
            'target' => $action->type === CaseActionType::Returned && $action->to_step !== null
                ? 'به مرحله‌ی '.($action->to_step + 1).' «'.($case->step($action->to_step)?->name ?? '').'»'
                : null,
        ], $actions);
    }

    private static function jalali(DateTimeInterface $date): string
    {
        return Jalali::fromCarbon(Carbon::instance($date)->setTimezone(config('app.timezone')))->format('Y/m/d H:i');
    }

    /**
     * @return array{subjectLabel: string, completionSummary: ?string, steps: list<array{number: int, name: string, assigneeIsUser: bool, assignee: string, actions: list<array{label: string, color: string}>, rejects: bool, returns: bool, notes: list<string>, openCases: int, state: ?string, history: list<array<string, mixed>>, waiting: ?string}>, completed: bool, started: list<array<string, mixed>>}
     */
    private static function stepsOf(WorkflowCase $case): array
    {
        $rejectedAt = $case->status === CaseStatus::Rejected
            ? $case->actions()->where('type', CaseActionType::Rejected)->latest('id')->value('from_step')
            : null;

        return self::chart(
            $case->stepList(),
            $case->subject_type,
            fn (): int => 0,
            fn (int $index): ?string => match ($case->status) {
                CaseStatus::Completed => self::DONE,
                CaseStatus::InProgress => match (true) {
                    $index < $case->current_step => self::DONE,
                    $index === $case->current_step => self::CURRENT,
                    default => null,
                },
                CaseStatus::Rejected => match (true) {
                    $rejectedAt === null => null,
                    $index < $rejectedAt => self::DONE,
                    $index === $rejectedAt => self::REJECTED,
                    default => null,
                },
            },
            completed: $case->status === CaseStatus::Completed,
        );
    }

    /**
     * @param  list<WorkflowStep>  $steps
     * @param  callable(int): int  $openCases
     * @param  callable(int): ?string  $state
     * @return array{subjectLabel: string, completionSummary: ?string, steps: list<array{number: int, name: string, assigneeIsUser: bool, assignee: string, actions: list<array{label: string, color: string}>, rejects: bool, returns: bool, notes: list<string>, openCases: int, state: ?string, history: list<array<string, mixed>>, waiting: ?string}>, completed: bool, started: list<array<string, mixed>>}
     */
    private static function chart(array $steps, string $subjectType, callable $openCases, callable $state, bool $completed): array
    {
        $subject = WorkflowSubjects::find($subjectType);

        return [
            'subjectLabel' => $subject?->label() ?? 'پرونده',
            'completionSummary' => $subject?->completionSummary(),
            'completed' => $completed,
            'started' => [],
            'steps' => collect($steps)
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
                    // e.g. the step's documents (ADR 0019).
                    'notes' => collect(WorkflowStepExtensions::all())
                        ->flatMap(fn (StepExtension $extension): array => $extension->summary($step, $subjectType))
                        ->values()
                        ->all(),
                    'openCases' => $openCases($index),
                    'state' => $state($index),
                    'history' => [],
                    'waiting' => null,
                ])
                ->values()
                ->all(),
        ];
    }
}

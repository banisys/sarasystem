<?php

namespace App\Workflow;

use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Runs workflow cases (ADR 0016). Every change of a case goes through here:
 * it checks that the actor is the current assignee and that the case is
 * still at the step they saw, writes the history and tells the next
 * assignees through database notifications.
 */
class WorkflowEngine
{
    /**
     * Start the workflow for a record, at its first step. The case belongs
     * to the record's tenant.
     */
    public function start(Workflow $workflow, Model $subject, ?User $actor = null): WorkflowCase
    {
        $steps = $workflow->stepList();

        if ($steps === []) {
            throw new WorkflowActionDenied('این فرایند هیچ مرحله‌ای ندارد.');
        }

        $handler = WorkflowSubjects::for($workflow->subject_type);

        return DB::transaction(function () use ($workflow, $subject, $actor, $steps, $handler): WorkflowCase {
            $case = WorkflowCase::query()->create([
                'tenant_id' => $subject->getAttribute('tenant_id'),
                'workflow_id' => $workflow->getKey(),
                'workflow_name' => $workflow->name,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'steps' => array_map(fn (WorkflowStep $step): array => $step->toArray(), $steps),
                'status' => CaseStatus::InProgress,
            ]);

            $case->setRelation('subject', $subject);
            $this->log($case, $actor, CaseActionType::Started, to: 0);
            $this->enter($case, 0, $handler);

            return $case;
        });
    }

    /**
     * Approve the current step: on to the next one, or, at the last step,
     * finish the case (the subject's completed() gets $data).
     *
     * @param  array<string, mixed>  $data
     */
    public function approve(WorkflowCase $case, User $actor, ?string $comment = null, array $data = []): void
    {
        $this->act($case, $actor, function (WorkflowCase $case, WorkflowSubject $handler) use ($actor, $comment, $data): void {
            $from = $case->current_step;

            if (! $case->isLastStep()) {
                $this->log($case, $actor, CaseActionType::Approved, $from, $from + 1, $comment);
                $this->enter($case, $from + 1, $handler);

                return;
            }

            $this->finish($case, CaseStatus::Completed);
            $this->log($case, $actor, CaseActionType::Completed, $from, comment: $comment);
            $handler->completed($case, $actor, $data);
        });
    }

    public function reject(WorkflowCase $case, User $actor, string $comment): void
    {
        $this->act($case, $actor, function (WorkflowCase $case, WorkflowSubject $handler) use ($actor, $comment): void {
            $this->ensureAllowed($case, StepAction::Reject);

            $from = $case->current_step;
            $this->finish($case, CaseStatus::Rejected);
            $this->log($case, $actor, CaseActionType::Rejected, $from, comment: $comment);
            $handler->rejected($case, $actor);
        });
    }

    /**
     * Send the case back to an earlier step.
     */
    public function returnTo(WorkflowCase $case, User $actor, int $step, string $comment): void
    {
        $this->act($case, $actor, function (WorkflowCase $case, WorkflowSubject $handler) use ($actor, $step, $comment): void {
            $this->ensureAllowed($case, StepAction::Return);

            if ($step < 0 || $step >= $case->current_step) {
                throw new WorkflowActionDenied('فقط به یکی از مرحله‌های قبل می‌توان برگرداند.');
            }

            $this->log($case, $actor, CaseActionType::Returned, $case->current_step, $step, $comment);
            $this->enter($case, $step, $handler);
        });
    }

    /**
     * Take the case, so it leaves the cartable of the role's other holders.
     */
    public function claim(WorkflowCase $case, User $actor): void
    {
        $this->act($case, $actor, function (WorkflowCase $case) use ($actor): void {
            if ($case->claimed_by_id !== null) {
                return;
            }

            $case->update(['claimed_by_id' => $actor->getKey(), 'claimed_at' => now()]);
            $this->log($case, $actor, CaseActionType::Claimed, $case->current_step);
        });
    }

    public function release(WorkflowCase $case, User $actor): void
    {
        $this->act($case, $actor, function (WorkflowCase $case) use ($actor): void {
            if ($case->claimed_by_id === null) {
                return;
            }

            $case->update(['claimed_by_id' => null, 'claimed_at' => null]);
            $this->log($case, $actor, CaseActionType::Released, $case->current_step);
        });
    }

    public function comment(WorkflowCase $case, User $actor, string $comment): void
    {
        $this->act($case, $actor, fn (WorkflowCase $case) => $this->log($case, $actor, CaseActionType::Commented, $case->current_step, comment: $comment));
    }

    /**
     * Change the subject through the subject's edit form; the history keeps
     * the old and new values.
     *
     * @param  array<string, mixed>  $data
     */
    public function edit(WorkflowCase $case, User $actor, array $data): void
    {
        $this->act($case, $actor, function (WorkflowCase $case, WorkflowSubject $handler) use ($actor, $data): void {
            $this->ensureAllowed($case, StepAction::Edit);

            $subject = $case->subject;
            $before = $subject->getRawOriginal();
            $handler->saveEdit($subject, $data);

            $changes = collect($subject->getChanges())
                ->except([$subject->getUpdatedAtColumn()])
                ->mapWithKeys(fn (mixed $new, string $attribute): array => [$attribute => [
                    'label' => $handler->attributeLabel($attribute),
                    'old' => $before[$attribute] ?? null,
                    'new' => $new,
                ]]);

            if ($changes->isNotEmpty()) {
                $this->log($case, $actor, CaseActionType::Edited, $case->current_step, changes: $changes->all());
            }
        });
    }

    /**
     * Note in the history that the subject was changed outside the
     * workflow, e.g. the applicant sent the form again.
     */
    public function recordSubjectUpdate(WorkflowCase $case, ?string $comment = null): void
    {
        $this->log($case, null, CaseActionType::SubjectUpdated, $case->current_step, comment: $comment);
    }

    /**
     * Whether the user may act on the case now: it is in progress and in
     * their cartable.
     */
    public function canAct(WorkflowCase $case, User $user): bool
    {
        return $case->isInProgress() && $case->isAssignedTo($user);
    }

    /**
     * Users whose cartable gets the step.
     *
     * @return Collection<int, User>
     */
    public function assignees(WorkflowStep $step): Collection
    {
        if ($step->assigneeType === AssigneeType::User) {
            return User::query()->whereKey($step->userId)->get();
        }

        return User::query()
            ->whereIn('id', fn (QueryBuilder $roles) => $roles
                ->select(config('permission.column_names.model_morph_key'))
                ->from(config('permission.table_names.model_has_roles'))
                ->where('model_type', (new User)->getMorphClass())
                ->where('role_id', $step->roleId)
                ->where('tenant_id', $step->tenantId))
            ->get();
    }

    /**
     * Lock the case, check the actor still may act on it at the step they
     * saw, then run the change.
     *
     * @param  Closure(WorkflowCase, WorkflowSubject<Model>): void  $change
     */
    private function act(WorkflowCase $case, User $actor, Closure $change): void
    {
        $seenStep = $case->current_step;

        DB::transaction(function () use ($case, $actor, $change, $seenStep): void {
            $locked = WorkflowCase::withoutTenantScope()->lockForUpdate()->findOrFail($case->getKey());

            if (! $locked->isInProgress() || $locked->current_step !== $seenStep) {
                throw new WorkflowActionDenied('این پرونده در این فاصله به مرحله‌ی دیگری رفته است.');
            }

            if (! $this->canAct($locked, $actor)) {
                throw new WorkflowActionDenied('این پرونده در کارتابل شما نیست.');
            }

            $change($locked, WorkflowSubjects::for($locked->subject_type));

            $case->setRawAttributes($locked->getAttributes(), sync: true);
        });
    }

    private function ensureAllowed(WorkflowCase $case, StepAction $action): void
    {
        if (! $case->currentStep()?->allows($action)) {
            throw new WorkflowActionDenied("«{$action->getLabel()}» در این مرحله مجاز نیست.");
        }
    }

    /**
     * @param  WorkflowSubject<Model>  $handler
     */
    private function enter(WorkflowCase $case, int $index, WorkflowSubject $handler): void
    {
        $step = $case->step($index) ?? throw new WorkflowActionDenied('مرحله پیدا نشد.');

        $case->update([
            'current_step' => $index,
            'assignee_tenant_id' => $step->tenantId,
            'assignee_role_id' => $step->roleId,
            'assignee_user_id' => $step->userId,
            'claimed_by_id' => null,
            'claimed_at' => null,
            'step_started_at' => now(),
        ]);

        $handler->moved($case);
        $this->notifyAssignees($case, $step, $handler);
    }

    private function finish(WorkflowCase $case, CaseStatus $status): void
    {
        $case->update([
            'status' => $status,
            'current_step' => null,
            'assignee_tenant_id' => null,
            'assignee_role_id' => null,
            'assignee_user_id' => null,
            'claimed_by_id' => null,
            'claimed_at' => null,
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  array<string, array{label: string, old: mixed, new: mixed}>|null  $changes
     */
    private function log(WorkflowCase $case, ?User $actor, CaseActionType $type, ?int $from = null, ?int $to = null, ?string $comment = null, ?array $changes = null): void
    {
        $case->actions()->create([
            'user_id' => $actor?->getKey(),
            'type' => $type,
            'from_step' => $from,
            'to_step' => $to,
            'comment' => filled($comment) ? $comment : null,
            'changes' => $changes,
        ]);
    }

    /**
     * Bell notification for everyone whose cartable just got the case. Sent
     * now, not queued: no queue worker is needed for the cartable to work.
     *
     * @param  WorkflowSubject<Model>  $handler
     */
    private function notifyAssignees(WorkflowCase $case, WorkflowStep $step, WorkflowSubject $handler): void
    {
        $tenant = Tenant::query()->find($step->tenantId);

        if ($tenant === null) {
            return;
        }

        $notification = Notification::make()
            ->title('پرونده‌ی جدید در کارتابل')
            ->body("{$handler->title($case->subject)} — مرحله‌ی «{$step->name}» در {$tenant->name}")
            ->icon('heroicon-o-inbox-arrow-down')
            ->actions([
                Action::make('open')
                    ->label('مشاهده')
                    ->url(WorkflowCaseResource::getUrl('view', ['record' => $case], panel: 'admin', tenant: $tenant))
                    ->markAsRead(),
            ]);

        foreach ($this->assignees($step) as $user) {
            $user->notifyNow($notification->toDatabase());
        }
    }
}

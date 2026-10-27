<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Workflow\CaseStatus;
use App\Workflow\WorkflowStep;
use App\Workflow\WorkflowSubjects;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * One record going through a workflow («پرونده»), ADR 0016. It belongs to
 * its subject's tenant, but its current step may sit in another tenant's
 * cartable: the cartable reads cases with assignedTo(), across owners.
 * Changed only through App\Workflow\WorkflowEngine.
 *
 * @property list<array<string, mixed>> $steps
 */
#[Fillable([
    'tenant_id', 'workflow_id', 'workflow_name', 'subject_type', 'subject_id', 'steps', 'status', 'current_step',
    'assignee_tenant_id', 'assignee_role_id', 'assignee_user_id', 'claimed_by_id', 'claimed_at', 'step_started_at', 'finished_at',
])]
class WorkflowCase extends Model
{
    use BelongsToTenant;

    /**
     * The record the case is about. Read without the tenant scope: the case
     * is shown in its assignee's tenant, which may not own the subject, and
     * whoever reached the case may see its subject.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return HasMany<WorkflowCaseAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowCaseAction::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function assigneeTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'assignee_tenant_id');
    }

    /**
     * The subject's title, e.g. «درخواست همکاری علی رضایی».
     */
    public function title(): string
    {
        $handler = WorkflowSubjects::find($this->subject_type);
        $subject = $this->subject;

        return $handler !== null && $subject !== null ? $handler->title($subject) : "پرونده‌ی {$this->getKey()}";
    }

    /**
     * @return list<WorkflowStep>
     */
    public function stepList(): array
    {
        return array_map(fn (array $step): WorkflowStep => WorkflowStep::fromArray($step), array_values($this->steps ?? []));
    }

    public function step(int $index): ?WorkflowStep
    {
        return $this->stepList()[$index] ?? null;
    }

    public function currentStep(): ?WorkflowStep
    {
        return $this->current_step === null ? null : $this->step($this->current_step);
    }

    public function isLastStep(): bool
    {
        return $this->current_step !== null && $this->current_step === count($this->steps) - 1;
    }

    public function isInProgress(): bool
    {
        return $this->status === CaseStatus::InProgress;
    }

    /**
     * Cases waiting for the user in the tenant's cartable: the current step
     * goes to them, or to a role they hold there, and nobody else has
     * claimed it. Across owner tenants (see the class comment).
     *
     * @return Builder<WorkflowCase>
     */
    public static function assignedTo(User $user, Tenant $tenant): Builder
    {
        return static::withoutTenantScope()
            ->where('status', CaseStatus::InProgress)
            ->where('assignee_tenant_id', $tenant->getKey())
            ->where(fn (Builder $query) => $query->whereNull('claimed_by_id')->orWhere('claimed_by_id', $user->getKey()))
            ->where(fn (Builder $query) => $query
                ->where('assignee_user_id', $user->getKey())
                ->orWhereIn('assignee_role_id', fn (QueryBuilder $roles) => $roles
                    ->select('role_id')
                    ->from(config('permission.table_names.model_has_roles'))
                    ->where(config('permission.column_names.model_morph_key'), $user->getKey())
                    ->where('model_type', $user->getMorphClass())
                    ->where('tenant_id', $tenant->getKey())));
    }

    public function isAssignedTo(User $user): bool
    {
        $tenant = $this->assignee_tenant_id === null ? null : Tenant::query()->find($this->assignee_tenant_id);

        return $tenant !== null && static::assignedTo($user, $tenant)->whereKey($this->getKey())->exists();
    }

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'steps' => 'array',
            'claimed_at' => 'datetime',
            'step_started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

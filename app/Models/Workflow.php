<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Workflow\WorkflowStep;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An installation-wide approval workflow («فرایند»): ordered steps,
 * each sent to a role or a person in a tenant (ADR 0016). `subject_type` is
 * the morph class of the records it runs on, registered by a module in
 * App\Workflow\WorkflowSubjects. Running cases keep a copy of the steps, so
 * editing a workflow only changes cases started afterwards.
 * The nullable legacy tenant_id is retained as migration history only.
 *
 * @property list<array<string, mixed>> $steps
 */
#[Fillable(['subject_type', 'name', 'description', 'is_active', 'steps'])]
class Workflow extends Model
{
    use HasFactory;

    /**
     * @return list<WorkflowStep>
     */
    public function stepList(): array
    {
        return array_map(fn (array $step): WorkflowStep => WorkflowStep::fromArray($step), array_values($this->steps ?? []));
    }

    /**
     * @param  Builder<Workflow>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Workflow>  $query
     */
    public function scopeFor(Builder $query, string $subjectType): void
    {
        $query->where('subject_type', $subjectType);
    }

    /**
     * @return HasMany<WorkflowCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(WorkflowCase::class)->withoutGlobalScope(TenantScope::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'steps' => 'array',
        ];
    }
}

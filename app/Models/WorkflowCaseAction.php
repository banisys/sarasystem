<?php

namespace App\Models;

use App\Workflow\CaseActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a case's history. Not tenant-scoped by itself: it is only
 * read through its case, which the reader may already see (the case may be
 * in another tenant's cartable than its owner's, ADR 0016).
 *
 * @property array<string, array{label: string, old: mixed, new: mixed}>|null $changes
 */
#[Fillable(['workflow_case_id', 'user_id', 'type', 'from_step', 'to_step', 'comment', 'changes'])]
class WorkflowCaseAction extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<WorkflowCase, $this>
     */
    public function workflowCase(): BelongsTo
    {
        return $this->belongsTo(WorkflowCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The step the action was taken in, by name.
     */
    public function stepName(): ?string
    {
        return $this->from_step === null ? null : $this->workflowCase?->step($this->from_step)?->name;
    }

    protected function casts(): array
    {
        return [
            'type' => CaseActionType::class,
            'changes' => 'array',
        ];
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowCase;

/**
 * The cartable is everyone's: a case is reached through its step
 * assignment (WorkflowCase::assignedTo), not through permissions, so the
 * resource is excluded from Shield's generation (ADR 0016). Cases are
 * changed only through WorkflowEngine.
 */
class WorkflowCasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkflowCase $workflowCase): bool
    {
        return $workflowCase->isAssignedTo($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, WorkflowCase $workflowCase): bool
    {
        return false;
    }

    public function delete(User $user, WorkflowCase $workflowCase): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}

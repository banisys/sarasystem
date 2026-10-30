<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Workflow\AssigneeType;
use App\Workflow\StepAction;

trait BuildsWorkflows
{
    /**
     * @param  list<array<string, mixed>>  $steps
     */
    protected function makeWorkflow(Tenant $context, string $subjectType, array $steps): Workflow
    {
        return $context->execute(fn (): Workflow => Workflow::factory()->create(['subject_type' => $subjectType, 'steps' => $steps]));
    }

    /**
     * @param  list<StepAction>  $actions
     * @return array<string, mixed>
     */
    protected function roleStep(string $name, Tenant $tenant, string $role, array $actions = [StepAction::Reject, StepAction::Return, StepAction::Edit]): array
    {
        return [
            'name' => $name,
            'tenant_id' => $tenant->id,
            'assignee_type' => AssigneeType::Role->value,
            'role_id' => $this->globalRole($role)->id,
            'user_id' => null,
            'actions' => array_map(fn (StepAction $action): string => $action->value, $actions),
        ];
    }

    protected function globalRole(string $name): Role
    {
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            return Role::findOrCreate($name, 'web');
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }

    /**
     * @param  list<StepAction>  $actions
     * @return array<string, mixed>
     */
    protected function userStep(string $name, Tenant $tenant, User $user, array $actions = [StepAction::Reject, StepAction::Return]): array
    {
        return [
            'name' => $name,
            'tenant_id' => $tenant->id,
            'assignee_type' => AssigneeType::User->value,
            'role_id' => null,
            'user_id' => $user->id,
            'actions' => array_map(fn (StepAction $action): string => $action->value, $actions),
        ];
    }
}

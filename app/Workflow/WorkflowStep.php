<?php

namespace App\Workflow;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;

/**
 * One step of a workflow, as stored in `workflows.steps` and copied into
 * `workflow_cases.steps` (ADR 0016).
 */
final readonly class WorkflowStep
{
    /**
     * @param  list<StepAction>  $actions
     */
    public function __construct(
        public string $name,
        public int $tenantId,
        public AssigneeType $assigneeType,
        public ?int $roleId,
        public ?int $userId,
        public array $actions,
    ) {}

    /**
     * @param  array{name?: string, tenant_id?: int|string, assignee_type?: string, role_id?: int|string|null, user_id?: int|string|null, actions?: list<string>}  $data
     */
    public static function fromArray(array $data): self
    {
        $assigneeType = AssigneeType::from($data['assignee_type'] ?? AssigneeType::Role->value);

        return new self(
            name: (string) ($data['name'] ?? ''),
            tenantId: (int) ($data['tenant_id'] ?? 0),
            assigneeType: $assigneeType,
            roleId: $assigneeType === AssigneeType::Role && filled($data['role_id'] ?? null) ? (int) $data['role_id'] : null,
            userId: $assigneeType === AssigneeType::User && filled($data['user_id'] ?? null) ? (int) $data['user_id'] : null,
            actions: array_values(array_filter(array_map(fn (string $action): ?StepAction => StepAction::tryFrom($action), $data['actions'] ?? []))),
        );
    }

    /**
     * @return array{name: string, tenant_id: int, assignee_type: string, role_id: int|null, user_id: int|null, actions: list<string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'tenant_id' => $this->tenantId,
            'assignee_type' => $this->assigneeType->value,
            'role_id' => $this->roleId,
            'user_id' => $this->userId,
            'actions' => array_map(fn (StepAction $action): string => $action->value, $this->actions),
        ];
    }

    public function allows(StepAction $action): bool
    {
        return in_array($action, $this->actions, true);
    }

    /**
     * e.g. «مدیر شعبه در شعبه‌ی غرب» or «علی رضایی در ستاد».
     */
    public function assigneeLabel(): string
    {
        $assignee = match ($this->assigneeType) {
            AssigneeType::Role => Role::query()->find($this->roleId)?->display_name ?? 'نقش حذف‌شده',
            AssigneeType::User => User::query()->find($this->userId)?->name ?? 'کاربر حذف‌شده',
        };

        $tenant = Tenant::query()->find($this->tenantId)?->name ?? '؟';

        return "{$assignee} در {$tenant}";
    }
}

<?php

namespace Modules\Employee\Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Workflow\AssigneeType;
use App\Workflow\StepAction;
use Database\Seeders\Concerns\GrantsDemoPermissions;
use Illuminate\Database\Seeder;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use RuntimeException;

/**
 * A sample hiring workflow for local development (ADR 0016), on top of the
 * core DemoSeeder's tenants, roles and users. Four steps across the head
 * office and a branch, chosen on three sample job positions. Safe to run
 * again: looks records up by name / title and resets the steps.
 */
class HiringDemoSeeder extends Seeder
{
    use GrantsDemoPermissions;

    public const WORKFLOW_NAME = 'استخدام کادر آموزشی (نمونه)';

    /** @var list<string> */
    public const POSITIONS = ['معلم ریاضی', 'معلم زبان انگلیسی', 'پشتیبان آموزشی'];

    public function run(): void
    {
        $headOffice = Tenant::primary() ?? throw new RuntimeException('No primary tenant: run the core seeder first.');
        $north = Tenant::query()->where('slug', 'north')->first() ?? throw new RuntimeException('Demo tenants missing: run the core seeder (DemoSeeder) first.');

        // Head office staff and branch managers see the employees' pages
        // (with their contracts and documents tabs).
        $this->grantDemoPermissions([
            'hq_member' => ['ViewAny:Employee', 'View:Employee'],
            'principal' => ['ViewAny:Employee', 'View:Employee', 'Update:Employee'],
        ]);

        $steps = [
            $this->roleStep('بررسی اولیه‌ی مدارک', $headOffice, 'hq_member', [StepAction::Reject, StepAction::Edit]),
            $this->roleStep('مصاحبه‌ی تخصصی', $north, 'principal', [StepAction::Reject, StepAction::Return]),
            $this->userStep('تأیید منابع انسانی ستاد', $headOffice, 'hq_member2.hq@sarasystem.test', [StepAction::Reject, StepAction::Return, StepAction::Edit]),
            $this->userStep('تأیید نهایی و استخدام', $headOffice, 'admin@sarasystem.test', [StepAction::Reject, StepAction::Return]),
        ];

        $headOffice->execute(function () use ($steps): void {
            $workflow = Workflow::query()->updateOrCreate(
                ['name' => self::WORKFLOW_NAME, 'subject_type' => (new JobApplication)->getMorphClass()],
                [
                    'description' => 'نمونه برای آزمایش: بررسی مدارک در ستاد، مصاحبه در شعبه شمال، تأیید منابع انسانی و تأیید نهایی.',
                    'is_active' => true,
                    'steps' => $steps,
                ],
            );

            foreach (self::POSITIONS as $sort => $title) {
                JobPosition::query()->updateOrCreate(
                    ['title' => $title],
                    ['workflow_id' => $workflow->getKey(), 'is_active' => true, 'sort' => $sort + 1],
                );
            }
        });
    }

    /**
     * @param  list<StepAction>  $actions
     * @return array<string, mixed>
     */
    private function roleStep(string $name, Tenant $tenant, string $role, array $actions): array
    {
        $roleId = Role::query()->whereNull('tenant_id')->where('name', $role)->value('id')
            ?? throw new RuntimeException("Demo role {$role} missing: run the core seeder (DemoSeeder) first.");

        return $this->step($name, $tenant, AssigneeType::Role, $roleId, null, $actions);
    }

    /**
     * @param  list<StepAction>  $actions
     * @return array<string, mixed>
     */
    private function userStep(string $name, Tenant $tenant, string $email, array $actions): array
    {
        $userId = User::query()->where('email', $email)->value('id')
            ?? throw new RuntimeException("Demo user {$email} missing: run the core seeder (DemoSeeder) first.");

        return $this->step($name, $tenant, AssigneeType::User, null, $userId, $actions);
    }

    /**
     * @param  list<StepAction>  $actions
     * @return array<string, mixed>
     */
    private function step(string $name, Tenant $tenant, AssigneeType $type, ?int $roleId, ?int $userId, array $actions): array
    {
        return [
            'name' => $name,
            'tenant_id' => $tenant->getKey(),
            'assignee_type' => $type->value,
            'role_id' => $roleId,
            'user_id' => $userId,
            'actions' => array_map(fn (StepAction $action): string => $action->value, $actions),
        ];
    }
}

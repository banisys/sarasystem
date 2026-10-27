<?php

namespace Modules\Student\Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Workflow\AssigneeType;
use App\Workflow\StepAction;
use Illuminate\Database\Seeder;
use Modules\Student\Models\Grade;
use Modules\Student\Models\Preregistration;
use RuntimeException;

/**
 * The Iranian school grades and a sample admission workflow for local
 * development (ADR 0018), on top of the core DemoSeeder's tenants, roles
 * and users. Safe to run again: looks records up by title / name and
 * resets the steps.
 */
class AdmissionDemoSeeder extends Seeder
{
    public const WORKFLOW_NAME = 'پذیرش دانش‌آموز (نمونه)';

    /** @var array<string, list<string>> stage => grades */
    public const GRADES = [
        'دبستان' => ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم'],
        'متوسطه اول' => ['هفتم', 'هشتم', 'نهم'],
        'متوسطه دوم' => ['دهم', 'یازدهم', 'دوازدهم'],
    ];

    public function run(): void
    {
        $headOffice = Tenant::primary() ?? throw new RuntimeException('No primary tenant: run the core seeder first.');
        $north = Tenant::query()->where('slug', 'north')->first() ?? throw new RuntimeException('Demo tenants missing: run the core seeder (DemoSeeder) first.');

        $steps = [
            $this->roleStep('بررسی مدارک', $headOffice, 'hq_member', [StepAction::Reject, StepAction::Edit]),
            $this->roleStep('مصاحبه و آزمون ورودی', $north, 'principal', [StepAction::Reject, StepAction::Return]),
            $this->userStep('تأیید نهایی و ثبت‌نام', $headOffice, 'admin@sarasystem.test', [StepAction::Reject, StepAction::Return, StepAction::Edit]),
        ];

        $headOffice->execute(function () use ($steps): void {
            $workflow = Workflow::query()->updateOrCreate(
                ['name' => self::WORKFLOW_NAME, 'subject_type' => (new Preregistration)->getMorphClass()],
                [
                    'description' => 'نمونه برای آزمایش: بررسی مدارک در ستاد، مصاحبه در شعبه شمال و تأیید نهایی.',
                    'is_active' => true,
                    'steps' => $steps,
                ],
            );

            $stageSort = 0;

            foreach (self::GRADES as $stageTitle => $grades) {
                $stage = Grade::query()->updateOrCreate(
                    ['title' => $stageTitle, 'parent_id' => null],
                    ['workflow_id' => $workflow->getKey(), 'is_active' => true, 'sort' => ++$stageSort],
                );

                foreach ($grades as $sort => $title) {
                    Grade::query()->updateOrCreate(
                        ['title' => $title, 'parent_id' => $stage->getKey()],
                        ['is_active' => true, 'sort' => $sort + 1],
                    );
                }
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

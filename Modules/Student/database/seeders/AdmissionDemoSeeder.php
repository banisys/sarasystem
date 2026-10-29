<?php

namespace Modules\Student\Database\Seeders;

use App\Models\FormCustomization;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Workflow\AssigneeType;
use App\Workflow\StepAction;
use Database\Seeders\Concerns\GrantsDemoPermissions;
use Illuminate\Database\Seeder;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use RuntimeException;

/**
 * A school's preregistration for local development (ADR 0018), on top of
 * the core DemoSeeder's tenants, roles and users: the grades as programs, a
 * three-step admission workflow, and the school-only questions of the LMS
 * student form (birth certificate, previous school, last average, …) as
 * added fields of the form. Safe to run again: looks records up by title /
 * name and resets the steps and the form setup.
 */
class AdmissionDemoSeeder extends Seeder
{
    use GrantsDemoPermissions;

    public const WORKFLOW_NAME = 'پذیرش دانش‌آموز (نمونه)';

    /** @var list<string> */
    public const PROGRAMS = [
        'پایه اول', 'پایه دوم', 'پایه سوم', 'پایه چهارم', 'پایه پنجم', 'پایه ششم',
        'پایه هفتم', 'پایه هشتم', 'پایه نهم', 'پایه دهم', 'پایه یازدهم', 'پایه دوازدهم',
    ];

    public function run(): void
    {
        $headOffice = Tenant::primary() ?? throw new RuntimeException('No primary tenant: run the core seeder first.');
        $north = Tenant::query()->where('slug', 'north')->first() ?? throw new RuntimeException('Demo tenants missing: run the core seeder (DemoSeeder) first.');

        // Head office staff and branch managers see the learners' pages
        // (with their contracts and documents tabs).
        $this->grantDemoPermissions([
            'hq_member' => ['ViewAny:Student', 'View:Student'],
            'principal' => ['ViewAny:Student', 'View:Student', 'Update:Student'],
        ]);

        $steps = [
            $this->roleStep('بررسی مدارک', $headOffice, 'hq_member', [StepAction::Reject, StepAction::Edit]),
            $this->roleStep('مصاحبه و آزمون ورودی', $north, 'principal', [StepAction::Reject, StepAction::Return]),
            $this->userStep('تأیید نهایی و ثبت‌نام', $headOffice, 'admin@sarasystem.test', [StepAction::Reject, StepAction::Return, StepAction::Edit]),
        ];

        $headOffice->execute(function () use ($headOffice, $steps): void {
            $workflow = Workflow::query()->updateOrCreate(
                ['name' => self::WORKFLOW_NAME, 'subject_type' => (new Preregistration)->getMorphClass()],
                [
                    'description' => 'نمونه برای آزمایش: بررسی مدارک در ستاد، مصاحبه در شعبه شمال و تأیید نهایی.',
                    'is_active' => true,
                    'steps' => $steps,
                ],
            );

            foreach (self::PROGRAMS as $sort => $title) {
                Program::query()->updateOrCreate(
                    ['title' => $title],
                    ['workflow_id' => $workflow->getKey(), 'is_active' => true, 'sort' => $sort + 1],
                );
            }

            FormCustomization::query()->updateOrCreate(
                ['tenant_id' => $headOffice->getKey(), 'form' => PreregistrationForm::KEY, 'owner_type' => null, 'owner_id' => null],
                [
                    'field_modes' => ['father_name' => 'required', 'phone' => 'required', 'city' => 'required', 'address' => 'required',
                        // A school asks the parents and whose mobile the child signs in with (ADR 0028).
                        'father' => 'required', 'mother' => 'optional', 'learner_mobile' => 'optional', 'login_mobile' => 'required'],
                    'fields' => [
                        ['key' => 'birth_certificate', 'label' => 'شماره شناسنامه', 'type' => 'text', 'required' => true, 'section' => 'personal'],
                        ['key' => 'birth_place', 'label' => 'محل تولد', 'type' => 'text', 'section' => 'personal'],
                        ['key' => 'previous_school', 'label' => 'نام مدرسه‌ی پیشین', 'type' => 'text', 'required' => true, 'section' => 'other'],
                        ['key' => 'last_average', 'label' => 'معدل ترم آخر', 'type' => 'number', 'section' => 'other'],
                    ],
                ],
            );
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

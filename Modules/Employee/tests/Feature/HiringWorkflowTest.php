<?php

namespace Modules\Employee\Tests\Feature;

use App\Filament\Resources\WorkflowCases\Pages\ViewWorkflowCase;
use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Models\FormCustomization;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Sms\SmsSender;
use App\Workflow\CaseActionType;
use App\Workflow\CaseStatus;
use App\Workflow\WorkflowActionDenied;
use App\Workflow\WorkflowEngine;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Filament\Resources\Employees\Pages\ListEmployees;
use Modules\Employee\Filament\Resources\JobApplications\Pages\ViewJobApplication;
use Modules\Employee\Forms\JobApplicationForm;
use Modules\Employee\Http\Requests\StoreJobApplicationRequest;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Modules\Employee\Tests\Concerns\SubmitsJobApplications;
use Modules\Employee\Workflow\JobApplicationSubject;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class HiringWorkflowTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, RefreshDatabase, SubmitsJobApplications;

    private Tenant $primary;

    private Tenant $west;

    private JobPosition $position;

    private FakeSmsSender $sms;

    private Workflow $workflow;

    private User $recruiter;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create(['name' => 'ستاد']);
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'غرب']);

        $this->recruiter = User::factory()->create();
        $this->makeStaff($this->recruiter, $this->primary);
        $this->grantRole($this->recruiter, $this->primary, 'recruiter');

        $this->director = User::factory()->create();
        $this->makeStaff($this->director, $this->primary);

        $this->workflow = $this->makeWorkflow($this->primary, (new JobApplication)->getMorphClass(), [
            $this->roleStep('بررسی اولیه', $this->primary, 'recruiter'),
            $this->userStep('تأیید مدیر', $this->primary, $this->director),
        ]);
        $this->position = JobPosition::factory()->for($this->primary)->create(['title' => 'معلم ریاضی', 'workflow_id' => $this->workflow->id]);
    }

    public function test_a_new_application_starts_its_positions_workflow(): void
    {
        $this->verifyMobile();
        $this->post('/careers', $this->validData())->assertSessionHasNoErrors();

        $application = JobApplication::withoutTenantScope()->sole();
        $case = $application->workflowCase;

        $this->assertSame(0, $case->current_step);
        $this->assertSame($this->primary->id, $case->tenant_id);
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertTrue(WorkflowCase::assignedTo($this->recruiter, $this->primary)->whereKey($case->id)->exists());
        $this->assertSame(1, $this->recruiter->notifications()->count());
    }

    public function test_a_position_without_a_workflow_only_files_the_application(): void
    {
        $this->position->update(['workflow_id' => null]);

        $this->verifyMobile();
        $this->post('/careers', $this->validData())->assertSessionHasNoErrors();

        $this->assertNull(JobApplication::withoutTenantScope()->sole()->workflowCase);
    }

    public function test_the_applicant_can_resend_the_form_until_a_reviewer_has_it(): void
    {
        $this->verifyMobile();
        $this->post('/careers', $this->validData(['national_code' => '0013542419']))->assertSessionHasNoErrors();
        $this->verifyMobile();
        $this->post('/careers', $this->validData(['national_code' => '0013542419', 'first_name' => 'مریم']))->assertSessionHasNoErrors();

        $application = JobApplication::withoutTenantScope()->sole();
        $this->assertSame('مریم', $application->first_name);
        $this->assertSame(1, WorkflowCase::withoutTenantScope()->count());
        $this->assertTrue($application->workflowCase->actions()->where('type', CaseActionType::SubjectUpdated)->exists());

        app(WorkflowEngine::class)->approve($application->workflowCase, $this->recruiter);
        $this->assertSame(ApplicationStatus::InReview, $application->refresh()->status);

        $this->verifyMobile();
        $this->post('/careers', $this->validData(['national_code' => '0013542419', 'first_name' => 'سارا']))
            ->assertSessionHasErrors(['national_code' => StoreJobApplicationRequest::UNDER_REVIEW_MESSAGE]);
        $this->assertSame('مریم', $application->refresh()->first_name);
    }

    public function test_the_cartable_shows_the_application(): void
    {
        $case = $this->startCase(JobApplication::factory()->for($this->primary)->create(['first_name' => 'نرگس', 'last_name' => 'کریمی']));

        $this->actingAs($this->recruiter)
            ->get(WorkflowCaseResource::getUrl('view', ['record' => $case], tenant: $this->primary))
            ->assertOk()
            ->assertSee('درخواست همکاری نرگس کریمی')
            ->assertSee('دانشگاه تهران');
    }

    public function test_the_last_approval_hires_the_applicant_into_the_chosen_tenant(): void
    {
        $application = JobApplication::factory()->for($this->primary)->create(['national_code' => '0013542419', 'mobile' => '09121112233']);
        $case = $this->caseAtLastStep($application);
        $teacher = $this->globalRole('teacher');

        $this->actingInPanel($this->director, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->callAction('approve', data: [
                'tenant_id' => $this->west->id,
                'employment_type' => EmploymentType::PartTime->value,
                'hired_at' => '2026-10-01',
                'personnel_code' => 'P-100',
                'role_ids' => [$teacher->id],
                'panel_access' => true,
            ])
            ->assertHasNoActionErrors();

        $user = User::query()->where('national_code', '0013542419')->sole();
        $this->assertSame('09121112233', $user->mobile);
        $this->assertNotNull($user->mobile_verified_at);
        $this->assertEqualsCanonicalizing(['teacher', Utils::getPanelUserRoleName()], $this->roleNamesIn($user, $this->west));

        $employee = Employee::withoutTenantScope()->sole();
        $this->assertSame($this->west->id, $employee->tenant_id);
        $this->assertSame($user->id, $employee->user_id);
        $this->assertSame('P-100', $employee->personnel_code);
        $this->assertSame(EmploymentType::PartTime, $employee->employment_type);
        $this->assertSame($application->job_title, $employee->job_title);

        $this->assertSame(ApplicationStatus::Hired, $application->refresh()->status);
        $this->assertNotNull($application->closed_at);
        $this->assertSame(CaseStatus::Completed, $case->refresh()->status);
    }

    public function test_an_applicant_without_an_email_is_hired_without_one(): void
    {
        // The organization's form may not ask for an email (ADR 0017).
        $application = JobApplication::factory()->for($this->primary)->create(['national_code' => '0013542419', 'email' => null]);
        $case = $this->caseAtLastStep($application);

        $this->actingInPanel($this->director, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->callAction('approve', data: [
                'tenant_id' => $this->primary->id,
                'employment_type' => EmploymentType::FullTime->value,
                'hired_at' => '2026-10-01',
                'role_ids' => [],
                'panel_access' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertNull(User::query()->where('national_code', '0013542419')->sole()->email);
    }

    public function test_answers_to_added_fields_can_be_edited_in_the_workflow(): void
    {
        $this->customize(null, [
            ['key' => 'kanoon', 'label' => 'عضو کانون هستید؟', 'type' => 'select', 'required' => true, 'options' => ['بله', 'خیر']],
            ['key' => 'start', 'label' => 'زمان آمادگی برای شروع', 'type' => 'date'],
        ]);
        $application = JobApplication::factory()->for($this->primary)->for($this->position)->create(['custom_fields' => [
            ['key' => 'kanoon', 'label' => 'عضو کانون هستید؟', 'type' => 'select', 'section' => null, 'value' => 'خیر'],
            ['key' => 'removed', 'label' => 'فیلد حذف‌شده', 'type' => 'text', 'section' => null, 'value' => 'بماند'],
        ]]);
        $case = $this->startCase($application);

        $this->actingInPanel($this->recruiter, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->mountAction('editSubject')
            ->assertActionDataSet(['custom.kanoon' => 'خیر'])
            ->setActionData(['custom.kanoon' => null])
            ->callMountedAction()
            ->assertHasActionErrors(['custom.kanoon' => 'required'])
            ->setActionData(['custom.kanoon' => 'بله', 'custom.start' => '2026-11-01'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $answers = collect($application->refresh()->custom_fields)->keyBy('key');
        $this->assertSame(['kanoon', 'start', 'removed'], $answers->keys()->all());
        $this->assertSame('بله', $answers['kanoon']['value']);
        $this->assertSame('2026-11-01', $answers['start']['value']);
        $this->assertSame('بماند', $answers['removed']['value']);

        $changes = $case->actions()->where('type', CaseActionType::Edited)->sole()->changes;
        $this->assertEquals(['label' => 'عضو کانون هستید؟', 'old' => 'خیر', 'new' => 'بله'], $changes['custom.kanoon']);
        $this->assertSame('1405/08/10', $changes['custom.start']['new']);
        $this->assertArrayNotHasKey('custom_fields', $changes);
    }

    public function test_moving_an_application_to_another_position_asks_that_positions_fields(): void
    {
        $other = JobPosition::factory()->for($this->primary)->create(['title' => 'سرایدار']);
        $this->customize($other, [['key' => 'shift', 'label' => 'شیفت شب را می‌پذیرم', 'type' => 'checkbox', 'required' => true]]);
        $application = JobApplication::factory()->for($this->primary)->for($this->position)->create();
        $case = $this->startCase($application);

        $this->actingInPanel($this->recruiter, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->mountAction('editSubject')
            ->setActionData(['job_position_id' => $other->id, 'custom.shift' => false])
            ->callMountedAction()
            ->assertHasActionErrors(['custom.shift'])
            ->setActionData(['custom.shift' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $application->refresh();
        $this->assertSame('سرایدار', $application->job_title);
        $this->assertEquals([['key' => 'shift', 'label' => 'شیفت شب را می‌پذیرم', 'type' => 'checkbox', 'section' => null, 'value' => true]], $application->custom_fields);
    }

    public function test_an_existing_user_with_the_national_code_is_reused(): void
    {
        $parent = User::factory()->create(['national_code' => '0013542419', 'name' => 'نام قبلی']);
        $this->grantRole($parent, $this->west, 'parent');
        $case = $this->caseAtLastStep(JobApplication::factory()->for($this->primary)->create(['national_code' => '0013542419']));

        $this->actingInPanel($this->director, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->callAction('approve', data: ['tenant_id' => $this->west->id, 'employment_type' => EmploymentType::FullTime->value, 'hired_at' => '2026-10-01', 'panel_access' => false])
            ->assertHasNoActionErrors();

        $this->assertSame(1, User::query()->where('national_code', '0013542419')->count());
        $this->assertSame('نام قبلی', $parent->refresh()->name);
        $this->assertSame($parent->id, Employee::withoutTenantScope()->sole()->user_id);
    }

    public function test_roles_the_approver_may_not_give_stop_the_hiring(): void
    {
        $principal = $this->roleWithPermissions('principal', ['Delete:Employee']);
        $case = $this->caseAtLastStep(JobApplication::factory()->for($this->primary)->create());
        $data = ['tenant_id' => $this->west->id, 'employment_type' => EmploymentType::FullTime->value, 'hired_at' => '2026-10-01', 'role_ids' => [$principal->id]];

        // Not offered in the form...
        $this->actingInPanel($this->director, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->callAction('approve', data: $data)
            ->assertHasActionErrors(['role_ids.0']);

        // ...and refused by the service.
        try {
            app(WorkflowEngine::class)->approve($case, $this->director, null, $data);
            $this->fail('Hiring should have been refused.');
        } catch (WorkflowActionDenied $exception) {
            $this->assertSame('شما اجازه‌ی دادن یکی از نقش‌های انتخاب‌شده را در غرب ندارید.', $exception->getMessage());
        }

        $this->assertSame(0, Employee::withoutTenantScope()->count());
        $this->assertSame(0, User::query()->where('national_code', $case->subject->national_code)->count());
        $this->assertSame(1, $case->refresh()->current_step);
    }

    public function test_rejecting_closes_the_application(): void
    {
        $application = JobApplication::factory()->for($this->primary)->create();
        $case = $this->startCase($application);

        app(WorkflowEngine::class)->reject($case, $this->recruiter, 'سابقه‌ی کافی ندارد');

        $this->assertSame(ApplicationStatus::Rejected, $application->refresh()->status);
        $this->assertNotNull($application->closed_at);
    }

    public function test_a_workflow_can_be_started_from_the_applicants_page(): void
    {
        $this->roleWithPermissions('hr_manager', ['ViewAny:JobApplication', 'View:JobApplication', 'Update:JobApplication']);
        $this->grantRole($this->director, $this->primary, 'hr_manager');
        $application = JobApplication::factory()->for($this->primary)->create();

        $this->actingInPanel($this->director, $this->primary);
        Livewire::test(ViewJobApplication::class, ['record' => $application->getRouteKey()])
            ->assertSee('ثبت‌شده')
            ->callAction('startWorkflow', data: ['workflow_id' => $this->workflow->id])
            ->assertHasNoActionErrors();

        $this->assertSame(0, $application->refresh()->workflowCase->current_step);

        Livewire::test(ViewJobApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionHidden('startWorkflow')
            ->assertSee('بررسی اولیه');
    }

    public function test_hiring_workflow_definitions_are_available_in_every_tenant(): void
    {
        $subject = new JobApplicationSubject;

        $this->assertTrue($subject->availableIn($this->primary->refresh()));
        $this->assertTrue($subject->availableIn($this->west->refresh()));
    }

    public function test_the_employees_list_shows_only_the_current_tenants_employees(): void
    {
        $this->roleWithPermissions('hr_manager', ['ViewAny:Employee', 'View:Employee', 'Update:Employee']);
        $manager = User::factory()->create();
        foreach ([$this->primary, $this->west] as $tenant) {
            $this->makeStaff($manager, $tenant);
            $this->grantRole($manager, $tenant, 'hr_manager');
        }
        $own = Employee::factory()->for($this->west)->create();
        $foreign = Employee::factory()->for($this->primary)->create();

        $this->actingInPanel($manager, $this->west);

        Livewire::test(ListEmployees::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function customize(?JobPosition $owner, array $fields): void
    {
        FormCustomization::withoutTenantScope()->create([
            'tenant_id' => $this->primary->id,
            'form' => JobApplicationForm::KEY,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->id,
            'field_modes' => [],
            'fields' => $fields,
        ]);
    }

    private function startCase(JobApplication $application): WorkflowCase
    {
        return app(WorkflowEngine::class)->start($this->workflow, $application);
    }

    private function caseAtLastStep(JobApplication $application): WorkflowCase
    {
        $case = $this->startCase($application);
        app(WorkflowEngine::class)->approve($case, $this->recruiter);

        return $case;
    }

    /**
     * @return list<string>
     */
    private function roleNamesIn(User $user, Tenant $tenant): array
    {
        return Role::query()
            ->whereIn('id', DB::table('model_has_roles')->where('model_id', $user->id)->where('tenant_id', $tenant->id)->pluck('role_id'))
            ->pluck('name')
            ->all();
    }
}

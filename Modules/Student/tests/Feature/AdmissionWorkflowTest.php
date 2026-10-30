<?php

namespace Modules\Student\Tests\Feature;

use App\Filament\Resources\WorkflowCases\Pages\ViewWorkflowCase;
use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Filament\Resources\Preregistrations\Pages\ViewPreregistration;
use Modules\Student\Http\Requests\StorePreregistrationRequest;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;
use Modules\Student\Tests\Concerns\SubmitsPreregistrations;
use Modules\Student\Workflow\PreregistrationSubject;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class AdmissionWorkflowTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, RefreshDatabase, SubmitsPreregistrations;

    private Tenant $primary;

    private Tenant $west;

    private Program $program;

    private FakeSmsSender $sms;

    private Workflow $workflow;

    private User $registrar;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create(['name' => 'ستاد']);
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'غرب']);

        $this->registrar = User::factory()->create();
        $this->makeStaff($this->registrar, $this->primary);
        $this->grantRole($this->registrar, $this->primary, 'registrar');

        $this->director = User::factory()->create();
        $this->makeStaff($this->director, $this->primary);

        $this->workflow = $this->makeWorkflow($this->primary, (new Preregistration)->getMorphClass(), [
            $this->roleStep('بررسی مدارک', $this->primary, 'registrar'),
            $this->userStep('تأیید مدیر', $this->primary, $this->director),
        ]);
        $this->program = Program::factory()->for($this->primary)->create(['title' => 'زبان انگلیسی - مقدماتی', 'workflow_id' => $this->workflow->id]);
    }

    public function test_a_new_preregistration_starts_its_programs_workflow(): void
    {
        $this->verifyMobile();
        $this->post('/preregister', $this->validData())->assertSessionHasNoErrors();

        $preregistration = Preregistration::withoutTenantScope()->sole();
        $case = $preregistration->workflowCase;

        $this->assertSame(0, $case->current_step);
        $this->assertSame($this->primary->id, $case->tenant_id);
        $this->assertSame(PreregistrationStatus::Submitted, $preregistration->status);
        $this->assertTrue(WorkflowCase::assignedTo($this->registrar, $this->primary)->whereKey($case->id)->exists());
        $this->assertSame(1, $this->registrar->notifications()->count());
    }

    public function test_a_program_without_a_workflow_only_files_the_preregistration(): void
    {
        $this->program->update(['workflow_id' => null]);

        $this->verifyMobile();
        $this->post('/preregister', $this->validData())->assertSessionHasNoErrors();

        $this->assertNull(Preregistration::withoutTenantScope()->sole()->workflowCase);
    }

    public function test_the_applicant_can_resend_the_form_until_a_reviewer_has_it(): void
    {
        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['national_code' => '0013542419']))->assertSessionHasNoErrors();
        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['national_code' => '0013542419', 'first_name' => 'آرش']))->assertSessionHasNoErrors();

        $preregistration = Preregistration::withoutTenantScope()->sole();
        $this->assertSame('آرش', $preregistration->first_name);
        $this->assertSame(1, WorkflowCase::withoutTenantScope()->count());
        $this->assertTrue($preregistration->workflowCase->actions()->where('type', CaseActionType::SubjectUpdated)->exists());

        app(WorkflowEngine::class)->approve($preregistration->workflowCase, $this->registrar);
        $this->assertSame(PreregistrationStatus::InReview, $preregistration->refresh()->status);

        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['national_code' => '0013542419', 'first_name' => 'کیان']))
            ->assertSessionHasErrors(['national_code' => StorePreregistrationRequest::UNDER_REVIEW_MESSAGE]);
        $this->assertSame('آرش', $preregistration->refresh()->first_name);
    }

    public function test_the_cartable_shows_the_preregistration(): void
    {
        $case = $this->startCase(Preregistration::factory()->for($this->primary)->create(['first_name' => 'نیما', 'last_name' => 'باقری', 'city' => 'رشت']));

        $this->actingAs($this->registrar)
            ->get(WorkflowCaseResource::getUrl('view', ['record' => $case], tenant: $this->primary))
            ->assertOk()
            ->assertSee('پیش‌ثبت‌نام نیما باقری')
            ->assertSee('رشت');
    }

    public function test_the_last_approval_admits_the_learner_into_the_chosen_tenant(): void
    {
        $preregistration = Preregistration::factory()->for($this->primary)->create([
            'national_code' => '0013542419', 'mobile' => '09121112233', 'program_id' => $this->program->id, 'preferred_tenant_id' => $this->west->id,
        ]);
        $case = $this->caseAtLastStep($preregistration);
        $student = $this->globalRole('student');

        $this->actingInPanel($this->director, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->mountAction('approve')
            ->assertActionDataSet(['tenant_id' => $this->west->id, 'role_ids' => [$student->id]])
            ->setActionData(['enrolled_at' => '2026-09-23', 'student_code' => 'S-100'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $user = User::query()->where('national_code', '0013542419')->sole();
        $this->assertSame('09121112233', $user->mobile);
        $this->assertNotNull($user->mobile_verified_at);
        $this->assertSame(['student'], $this->roleNamesIn($user, $this->west));

        $row = Student::withoutTenantScope()->sole();
        $this->assertSame($this->west->id, $row->tenant_id);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame($preregistration->id, $row->preregistration_id);
        $this->assertSame('S-100', $row->student_code);
        $this->assertSame(StudentStatus::Active, $row->status);

        $this->assertSame(PreregistrationStatus::Admitted, $preregistration->refresh()->status);
        $this->assertNotNull($preregistration->closed_at);
        $this->assertSame(CaseStatus::Completed, $case->refresh()->status);
    }

    public function test_a_foreign_learner_keeps_their_twelve_digit_code(): void
    {
        $preregistration = Preregistration::factory()->for($this->primary)->create(['nationality' => 'foreign', 'national_code' => '123456789012']);
        $case = $this->caseAtLastStep($preregistration);

        app(WorkflowEngine::class)->approve($case, $this->director, null, [
            'tenant_id' => $this->west->id, 'enrolled_at' => '2026-09-23',
        ]);

        $this->assertSame('123456789012', Student::withoutTenantScope()->sole()->user->national_code);
    }

    public function test_an_existing_user_with_the_national_code_is_reused(): void
    {
        $child = User::factory()->create(['national_code' => '0013542419', 'name' => 'نام قبلی']);
        $this->grantRole($child, $this->primary, 'student');
        $case = $this->caseAtLastStep(Preregistration::factory()->for($this->primary)->create(['national_code' => '0013542419']));

        app(WorkflowEngine::class)->approve($case, $this->director, null, [
            'tenant_id' => $this->west->id, 'enrolled_at' => '2026-09-23',
        ]);

        $this->assertSame(1, User::query()->where('national_code', '0013542419')->count());
        $this->assertSame('نام قبلی', $child->refresh()->name);
        $this->assertSame($child->id, Student::withoutTenantScope()->sole()->user_id);
    }

    public function test_a_learner_is_not_admitted_twice_into_one_tenant(): void
    {
        $user = User::factory()->create(['national_code' => '0013542419']);
        Student::factory()->for($this->west)->for($user)->create();
        $case = $this->caseAtLastStep(Preregistration::factory()->for($this->primary)->create(['national_code' => '0013542419']));

        $this->expectException(WorkflowActionDenied::class);

        app(WorkflowEngine::class)->approve($case, $this->director, null, [
            'tenant_id' => $this->west->id, 'enrolled_at' => '2026-09-23',
        ]);
    }

    public function test_roles_the_approver_may_not_give_stop_the_admission(): void
    {
        $principal = $this->roleWithPermissions('principal', ['Delete:Student']);
        $case = $this->caseAtLastStep(Preregistration::factory()->for($this->primary)->create());

        try {
            app(WorkflowEngine::class)->approve($case, $this->director, null, [
                'tenant_id' => $this->west->id, 'enrolled_at' => '2026-09-23', 'role_ids' => [$principal->id],
            ]);
            $this->fail('Admission should have been refused.');
        } catch (WorkflowActionDenied $exception) {
            $this->assertSame('شما اجازه‌ی دادن یکی از نقش‌های انتخاب‌شده را در غرب ندارید.', $exception->getMessage());
        }

        $this->assertSame(0, Student::withoutTenantScope()->count());
        $this->assertSame(1, $case->refresh()->current_step);
    }

    public function test_the_program_can_be_changed_in_the_workflow(): void
    {
        $other = Program::factory()->for($this->primary)->create(['title' => 'زبان آلمانی - مقدماتی']);
        $preregistration = Preregistration::factory()->for($this->primary)->create(['program_id' => $this->program->id]);
        $case = $this->startCase($preregistration);

        $this->actingInPanel($this->registrar, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->mountAction('editSubject')
            ->assertActionDataSet(['program_id' => $this->program->id])
            ->setActionData(['program_id' => $other->id, 'city' => 'کرج'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $preregistration->refresh();
        $this->assertSame('زبان آلمانی - مقدماتی', $preregistration->program_title);
        $this->assertSame('کرج', $preregistration->city);
        $this->assertSame('عنوان برنامه‌ی آموزشی', $case->actions()->where('type', CaseActionType::Edited)->sole()->changes['program_title']['label']);
    }

    public function test_rejecting_closes_the_preregistration(): void
    {
        $preregistration = Preregistration::factory()->for($this->primary)->create();
        $case = $this->startCase($preregistration);

        app(WorkflowEngine::class)->reject($case, $this->registrar, 'ظرفیت تکمیل است');

        $this->assertSame(PreregistrationStatus::Rejected, $preregistration->refresh()->status);
        $this->assertNotNull($preregistration->closed_at);
    }

    public function test_a_workflow_can_be_started_from_the_preregistrations_page(): void
    {
        $this->roleWithPermissions('admissions', ['ViewAny:Preregistration', 'View:Preregistration', 'Update:Preregistration']);
        $this->grantRole($this->director, $this->primary, 'admissions');
        $preregistration = Preregistration::factory()->for($this->primary)->create(['program_id' => $this->program->id]);

        $this->actingInPanel($this->director, $this->primary);
        Livewire::test(ViewPreregistration::class, ['record' => $preregistration->getRouteKey()])
            ->mountAction('startWorkflow')
            ->assertActionDataSet(['workflow_id' => $this->workflow->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(0, $preregistration->refresh()->workflowCase->current_step);

        Livewire::test(ViewPreregistration::class, ['record' => $preregistration->getRouteKey()])
            ->assertActionHidden('startWorkflow')
            ->assertSee('بررسی مدارک');
    }

    public function test_admission_workflow_definitions_are_available_in_every_tenant(): void
    {
        $subject = new PreregistrationSubject;

        $this->assertTrue($subject->availableIn($this->primary->refresh()));
        $this->assertTrue($subject->availableIn($this->west->refresh()));
    }

    private function startCase(Preregistration $preregistration): WorkflowCase
    {
        return app(WorkflowEngine::class)->start($this->workflow, $preregistration);
    }

    private function caseAtLastStep(Preregistration $preregistration): WorkflowCase
    {
        $case = $this->startCase($preregistration);
        app(WorkflowEngine::class)->approve($case, $this->registrar);

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

<?php

namespace Modules\Contract\Tests\Feature;

use App\Filament\Resources\WorkflowCases\Pages\ViewWorkflowCase;
use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Filament\Resources\Workflows\WorkflowResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Sms\SmsSender;
use App\Workflow\CaseActionType;
use App\Workflow\WorkflowActionDenied;
use App\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Tests\Concerns\MakesContracts;
use Modules\Contract\Workflow\ContractStepExtension;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Student;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * Contracts prepared in workflow steps and issued on acceptance (ADR 0020).
 */
class ContractWorkflowTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, MakesContracts, RefreshDatabase;

    private Tenant $primary;

    private Tenant $branch;

    private User $recruiter;

    private User $director;

    private ContractType $employment;

    private Workflow $workflow;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create(['name' => 'ستاد']);
        $this->primary->makePrimary();
        $this->branch = Tenant::factory()->create(['name' => 'شعبه شمال']);

        $this->recruiter = User::factory()->create();
        $this->makeStaff($this->recruiter, $this->primary);
        $this->grantRole($this->recruiter, $this->primary, 'recruiter');

        $this->director = User::factory()->create();
        $this->makeStaff($this->director, $this->primary);

        $this->employment = $this->contractType($this->primary, ['title' => 'قرارداد کارمند', 'parties' => ['employee'], 'number_prefix' => 'K-']);

        $prepare = $this->roleStep('تنظیم قرارداد', $this->primary, 'recruiter');
        $prepare['extensions'] = [ContractStepExtension::KEY => ['prepare' => [$this->employment->id]]];

        $this->workflow = $this->makeWorkflow($this->primary, (new JobApplication)->getMorphClass(), [
            $prepare,
            $this->userStep('تأیید نهایی', $this->primary, $this->director),
        ]);
    }

    public function test_a_step_is_not_approved_until_its_contracts_are_prepared(): void
    {
        $case = $this->startCase();

        try {
            app(WorkflowEngine::class)->approve($case, $this->recruiter);
            $this->fail('Approving should have been refused.');
        } catch (WorkflowActionDenied $exception) {
            $this->assertSame('این قراردادها هنوز تنظیم نشده‌اند: قرارداد کارمند.', $exception->getMessage());
        }

        $this->assertSame(0, $case->refresh()->current_step);
    }

    public function test_the_assignee_prepares_the_contract_in_the_cartable(): void
    {
        $case = $this->startCase();
        $path = "type_{$this->employment->id}";

        $this->actingInPanel($this->recruiter, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->assertActionVisible('prepareContracts')
            ->assertActionHidden('previewContracts')
            ->callAction('prepareContracts', data: [$path => ['starts_on' => '2026-11-01', 'amount' => 900000000]])
            ->assertHasNoActionErrors()
            ->assertActionVisible('previewContracts')
            ->callAction('approve')
            ->assertHasNoActionErrors();

        $contract = Contract::withoutTenantScope()->sole();
        $this->assertSame(ContractStatus::Draft, $contract->status);
        $this->assertTrue($contract->contractable->is($case->subject));
        $this->assertSame($this->primary->id, $contract->tenant_id);
        $this->assertNull($contract->user_id);
        $this->assertSame(900000000, $contract->amount);
        $this->assertSame(1, $case->refresh()->current_step);
        $this->assertTrue($case->actions()->where('type', CaseActionType::Commented)->where('comment', 'قرارداد تنظیم شد: قرارداد کارمند')->exists());

        // Preparing again changes the same draft.
        $this->actingInPanel($this->recruiter, $this->primary);
        app(WorkflowEngine::class)->returnTo($case->refresh(), $this->director, 0, 'مبلغ را اصلاح کنید');
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->mountAction('prepareContracts')
            ->assertActionDataSet([$path.'.amount' => 900000000])
            ->setActionData([$path => ['starts_on' => '2026-11-01', 'amount' => 950000000]])
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertSame(950000000, Contract::withoutTenantScope()->sole()->amount);
    }

    public function test_hiring_moves_the_contract_to_the_employee_and_issues_it(): void
    {
        $case = $this->startCase();
        $this->draftFor($case->subject, $this->employment);
        app(WorkflowEngine::class)->approve($case, $this->recruiter);

        app(WorkflowEngine::class)->approve($case->refresh(), $this->director, null, [
            'tenant_id' => $this->branch->id, 'employment_type' => 'full_time', 'hired_at' => '2026-11-01', 'panel_access' => false,
        ]);

        $employee = Employee::withoutTenantScope()->sole();
        $contract = Contract::withoutTenantScope()->sole();
        $this->assertTrue($contract->contractable->is($employee));
        $this->assertSame($this->branch->id, $contract->tenant_id);
        $this->assertSame($employee->user_id, $contract->user_id);
        $this->assertSame(ContractStatus::Issued, $contract->status);
        $this->assertSame('K-1', $contract->number);
        $this->assertSame($this->director->id, $contract->issued_by_id);
        $this->assertStringContainsString('شعبه شمال', $contract->body);
        $this->assertStringContainsString($case->subject->national_code, $contract->body);
        $this->assertTrue(collect($this->sms->sent)->contains(fn (array $sms): bool => str_contains($sms['message'], 'قرارداد کارمند')));
    }

    public function test_admission_moves_a_learners_contract_too(): void
    {
        $tuition = $this->contractType($this->primary, [
            'title' => 'قرارداد شهریه',
            'parties' => ['learner'],
            'body' => '<p><span data-type="mergeTag" data-id="person_name"></span> فرزند <span data-type="mergeTag" data-id="father_name"></span>، <span data-type="mergeTag" data-id="program"></span></p>',
        ]);
        $preregistration = Preregistration::factory()->for($this->primary)->create();
        $this->draftFor($preregistration, $tuition);
        $workflow = $this->makeWorkflow($this->primary, (new Preregistration)->getMorphClass(), [$this->userStep('پذیرش', $this->primary, $this->director)]);
        $case = app(WorkflowEngine::class)->start($workflow, $preregistration);

        app(WorkflowEngine::class)->approve($case, $this->director, null, ['tenant_id' => $this->branch->id, 'enrolled_at' => '2026-09-23']);

        $contract = Contract::withoutTenantScope()->sole();
        $this->assertTrue($contract->contractable->is(Student::withoutTenantScope()->sole()));
        $this->assertSame(ContractStatus::Issued, $contract->status);
        $this->assertSame("{$preregistration->fullName()} فرزند {$preregistration->father_name}، زبان انگلیسی - مقدماتی", strip_tags($contract->body));
    }

    public function test_the_cartable_lists_the_contracts_and_the_diagram_names_them(): void
    {
        $case = $this->startCase();
        $this->draftFor($case->subject, $this->employment);

        $this->actingAs($this->recruiter);
        $this->get(WorkflowCaseResource::getUrl('view', ['record' => $case], tenant: $this->primary))
            ->assertOk()
            ->assertSee('قراردادها')
            ->assertSee('پیش‌نویس');

        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);
        $this->actingAs($admin)
            ->get(WorkflowResource::getUrl('view', ['record' => $this->workflow], tenant: $this->primary))
            ->assertOk()
            ->assertSee('تنظیم قرارداد: قرارداد کارمند');
    }

    private function startCase(): WorkflowCase
    {
        return app(WorkflowEngine::class)->start($this->workflow, JobApplication::factory()->for($this->primary)->create());
    }
}

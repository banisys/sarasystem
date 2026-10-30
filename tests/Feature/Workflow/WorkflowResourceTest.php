<?php

namespace Tests\Feature\Workflow;

use App\Filament\Resources\Workflows\Pages\CreateWorkflow;
use App\Filament\Resources\Workflows\Pages\ListWorkflows;
use App\Filament\Resources\Workflows\WorkflowResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Workflow\AssigneeType;
use App\Workflow\CaseStatus;
use App\Workflow\StepAction;
use App\Workflow\WorkflowSubjects;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Employee\Models\JobApplication;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fixtures\TenantNoteSubject;
use Tests\TestCase;

class WorkflowResourceTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, RefreshDatabase;

    private Tenant $head;

    private Tenant $west;

    private User $designer;

    private TenantNoteSubject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new TenantNoteSubject;
        WorkflowSubjects::register($this->subject);

        $this->head = Tenant::factory()->create();
        $this->west = Tenant::factory()->create();

        $this->roleWithPermissions('process_designer', ['ViewAny:Workflow', 'View:Workflow', 'Create:Workflow', 'Update:Workflow', 'Delete:Workflow']);
        $this->designer = User::factory()->create();
        foreach ([$this->head, $this->west] as $tenant) {
            $this->makeStaff($this->designer, $tenant);
            $this->grantRole($this->designer, $tenant, 'process_designer');
        }
    }

    public function test_a_workflow_is_designed_with_role_and_person_steps(): void
    {
        $this->grantRole($person = User::factory()->create(), $this->west, 'teacher');
        $reviewer = $this->globalRole('reviewer');
        $undoRepeaterFake = Repeater::fake();

        $this->actingInPanel($this->designer, $this->head);

        Livewire::test(CreateWorkflow::class)
            ->fillForm([
                'name' => 'بررسی یادداشت',
                'subject_type' => $this->subject->type(),
                'is_active' => true,
                'steps' => [
                    ['name' => 'بررسی', 'tenant_id' => $this->west->id, 'assignee_type' => AssigneeType::Role->value, 'role_id' => $reviewer->id, 'actions' => ['reject']],
                    ['name' => 'تأیید', 'tenant_id' => $this->west->id, 'assignee_type' => AssigneeType::User->value, 'user_id' => $person->id, 'actions' => []],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $workflow = Workflow::query()->sole();
        $this->assertNull($workflow->tenant_id);
        [$first, $second] = $workflow->stepList();
        $this->assertSame($reviewer->id, $first->roleId);
        $this->assertSame($this->west->id, $first->tenantId);
        $this->assertSame($person->id, $second->userId);
        $this->assertNull($second->roleId);
    }

    public function test_a_person_step_needs_a_member_of_the_steps_tenant(): void
    {
        $outsider = User::factory()->create();
        $this->grantRole($outsider, $this->head, 'teacher');
        $undoRepeaterFake = Repeater::fake();

        $this->actingInPanel($this->designer, $this->head);

        Livewire::test(CreateWorkflow::class)
            ->fillForm([
                'name' => 'بررسی',
                'subject_type' => $this->subject->type(),
                'steps' => [
                    ['name' => 'تأیید', 'tenant_id' => $this->west->id, 'assignee_type' => AssigneeType::User->value, 'user_id' => $outsider->id],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['steps.0.user_id']);

        $undoRepeaterFake();
        $this->assertSame(0, Workflow::query()->withoutGlobalScopes()->count());
    }

    public function test_every_tenant_lists_and_opens_the_same_workflow_definitions(): void
    {
        $own = $this->makeWorkflow($this->head, $this->subject->type(), [$this->roleStep('بررسی', $this->head, 'reviewer')]);
        $foreign = $this->makeWorkflow($this->west, $this->subject->type(), [$this->roleStep('بررسی', $this->west, 'reviewer')]);

        $this->actingInPanel($this->designer, $this->head);

        Livewire::test(ListWorkflows::class)->assertCanSeeTableRecords([$own, $foreign]);
        $this->get(WorkflowResource::getUrl('edit', ['record' => $foreign], tenant: $this->head))->assertOk()->assertSee('مستقل از شعبه');

        $this->actingInPanel($this->designer, $this->west);
        Livewire::test(ListWorkflows::class)->assertCanSeeTableRecords([$own, $foreign]);
        $this->get(WorkflowResource::getUrl('view', ['record' => $own], tenant: $this->west))->assertOk()->assertSee('مستقل از شعبه');
    }

    public function test_the_workflow_page_draws_its_steps(): void
    {
        $person = User::factory()->create(['name' => 'رضا مدیری']);
        $this->grantRole($person, $this->head, 'teacher');
        $workflow = $this->makeWorkflow($this->head, $this->subject->type(), [
            $this->roleStep('بررسی شعبه', $this->west, 'reviewer', [StepAction::Reject]),
            $this->userStep('تأیید ستاد', $this->head, $person, [StepAction::Return]),
        ]);
        $this->globalRole('reviewer')->fill(['label' => 'بازبین'])->save();

        $this->actingAs($this->designer)
            ->get(WorkflowResource::getUrl('view', ['record' => $workflow], tenant: $this->head))
            ->assertOk()
            ->assertSeeInOrder(['شروع', 'بررسی شعبه', 'بازبین در '.$this->west->name, 'رد ← پایان', 'تأیید ستاد', 'رضا مدیری در '.$this->head->name, 'می‌تواند به مرحله‌ی قبل برگرداند', 'پایان: تأیید نهایی']);
    }

    public function test_staff_without_permission_cannot_design_workflows(): void
    {
        $clerk = User::factory()->create();
        $this->makeStaff($clerk, $this->head);

        $this->actingAs($clerk)->get(WorkflowResource::getUrl('index', tenant: $this->head))->assertForbidden();
    }

    public function test_hiring_workflows_can_be_created_from_a_branch_without_an_owner_tenant(): void
    {
        $this->actingInPanel($this->designer, $this->west);
        $reviewer = $this->globalRole('reviewer');
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateWorkflow::class)
            ->fillForm([
                'name' => 'فرایند استخدام مشترک',
                'subject_type' => (new JobApplication)->getMorphClass(),
                'steps' => [['name' => 'بررسی', 'tenant_id' => $this->head->id, 'assignee_type' => 'role', 'role_id' => $reviewer->id, 'actions' => []]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();
        $workflow = Workflow::query()->sole();
        $this->assertNull($workflow->tenant_id);
        $this->assertSame($this->head->id, $workflow->stepList()[0]->tenantId);
    }

    public function test_workflow_counts_include_cases_from_all_tenants_without_unscoping_case_queries(): void
    {
        $workflow = $this->makeWorkflow($this->head, $this->subject->type(), [$this->roleStep('بررسی', $this->west, 'reviewer')]);
        foreach ([$this->head, $this->west] as $tenant) {
            WorkflowCase::query()->create([
                'tenant_id' => $tenant->id, 'workflow_id' => $workflow->id, 'workflow_name' => $workflow->name,
                'subject_type' => $this->subject->type(), 'subject_id' => 1, 'steps' => $workflow->steps,
                'status' => CaseStatus::InProgress, 'current_step' => 0,
            ]);
        }

        $this->actingInPanel($this->designer, $this->head);

        Livewire::test(ListWorkflows::class)->assertTableColumnStateSet('open_cases_count', 2, $workflow);
        $this->assertSame(1, WorkflowCase::query()->count());
        $this->assertSame(2, $workflow->cases()->count());
    }
}

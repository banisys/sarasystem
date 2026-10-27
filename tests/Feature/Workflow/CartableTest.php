<?php

namespace Tests\Feature\Workflow;

use App\Filament\Resources\WorkflowCases\Pages\ListWorkflowCases;
use App\Filament\Resources\WorkflowCases\Pages\ViewWorkflowCase;
use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Workflow\CaseStatus;
use App\Workflow\StepAction;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowSubjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fixtures\TenantNote;
use Tests\Fixtures\TenantNoteSubject;
use Tests\TestCase;

class CartableTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, RefreshDatabase;

    private Tenant $head;

    private Tenant $west;

    private User $reviewer;

    private User $manager;

    private TenantNoteSubject $subject;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        TenantNote::createTable();
        $this->subject = new TenantNoteSubject;
        WorkflowSubjects::register($this->subject);

        $this->head = Tenant::factory()->create(['name' => 'ستاد']);
        $this->west = Tenant::factory()->create(['name' => 'غرب']);

        $this->reviewer = User::factory()->create();
        $this->makeStaff($this->reviewer, $this->west);
        $this->grantRole($this->reviewer, $this->west, 'reviewer');

        $this->manager = User::factory()->create();
        $this->makeStaff($this->manager, $this->head);

        $this->workflow = $this->makeWorkflow($this->head, $this->subject->type(), [
            $this->roleStep('بررسی شعبه', $this->west, 'reviewer'),
            $this->userStep('تأیید ستاد', $this->head, $this->manager, [StepAction::Reject, StepAction::Return]),
        ]);
    }

    public function test_the_cartable_lists_only_cases_waiting_for_the_user_in_the_current_tenant(): void
    {
        $mine = $this->startCase('مال من');
        $atManager = $this->startCase('در ستاد');
        app(WorkflowEngine::class)->approve($atManager, $this->reviewer);

        // The reviewer is also staff in the head office, without the role there.
        $this->makeStaff($this->reviewer, $this->head);

        $this->actingInPanel($this->reviewer, $this->west);
        Livewire::test(ListWorkflowCases::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$atManager])
            ->assertSee('یادداشت مال من');

        $this->actingInPanel($this->reviewer, $this->head);
        Livewire::test(ListWorkflowCases::class)->assertCanNotSeeTableRecords([$mine, $atManager]);

        $this->actingAs($this->reviewer)->get(WorkflowCaseResource::getUrl('view', ['record' => $mine], tenant: $this->head))->assertNotFound();
        $this->actingAs($this->reviewer)->get(WorkflowCaseResource::getUrl('view', ['record' => $atManager], tenant: $this->west))->assertNotFound();
    }

    public function test_a_case_shows_its_subject_and_history(): void
    {
        $case = $this->startCase('گزارش ماهانه');

        $this->actingAs($this->reviewer)
            ->get(WorkflowCaseResource::getUrl('view', ['record' => $case], tenant: $this->west))
            ->assertOk()
            ->assertSee('یادداشت گزارش ماهانه')
            ->assertSee('بررسی شعبه')
            ->assertSee('شروع فرایند');
    }

    public function test_the_assignee_approves_and_the_case_moves_on(): void
    {
        $case = $this->startCase('اول');
        $this->actingInPanel($this->reviewer, $this->west);

        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->callAction('approve', data: ['comment' => 'تأیید شد'])
            ->assertHasNoActionErrors()
            ->assertRedirect(WorkflowCaseResource::getUrl('index', tenant: $this->west));

        $this->assertSame(1, $case->refresh()->current_step);
        $this->assertSame(1, $this->manager->notifications()->count());
    }

    public function test_the_last_approval_asks_the_subjects_completion_fields(): void
    {
        $case = $this->startCase('اول');
        app(WorkflowEngine::class)->approve($case, $this->reviewer);
        $this->actingInPanel($this->manager, $this->head);

        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->assertActionVisible('reject')
            ->assertActionVisible('return')
            ->assertActionHidden('editSubject')
            ->callAction('approve', data: ['code' => ''])
            ->assertHasActionErrors(['code' => 'required']);

        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->callAction('approve', data: ['code' => 'X9'])
            ->assertHasNoActionErrors();

        $this->assertSame(CaseStatus::Completed, $case->refresh()->status);
        $this->assertSame(['event' => 'completed', 'data' => ['code' => 'X9']], end($this->subject->events));
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $case = $this->startCase('اول');
        $this->actingInPanel($this->reviewer, $this->west);

        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->callAction('reject', data: ['comment' => ''])
            ->assertHasActionErrors(['comment' => 'required']);

        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->callAction('reject', data: ['comment' => 'ناقص'])
            ->assertHasNoActionErrors();

        $this->assertSame(CaseStatus::Rejected, $case->refresh()->status);
    }

    public function test_the_subject_can_be_edited_where_the_step_allows_it(): void
    {
        $case = $this->startCase('اول');
        $this->actingInPanel($this->reviewer, $this->west);

        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            // Nothing to go back to at the first step.
            ->assertActionHidden('return')
            ->callAction('editSubject', data: ['title' => 'اصلاح‌شده'])
            ->assertHasNoActionErrors();

        $this->assertSame('اصلاح‌شده', TenantNote::withoutTenantScope()->find($case->subject_id)->title);
    }

    private function startCase(string $title): WorkflowCase
    {
        $note = TenantNote::query()->create(['title' => $title, 'tenant_id' => $this->head->id]);

        return app(WorkflowEngine::class)->start($this->workflow, $note);
    }
}

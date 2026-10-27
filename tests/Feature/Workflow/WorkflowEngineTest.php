<?php

namespace Tests\Feature\Workflow;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Workflow\CaseActionType;
use App\Workflow\CaseStatus;
use App\Workflow\WorkflowActionDenied;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowSubjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fixtures\TenantNote;
use Tests\Fixtures\TenantNoteSubject;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, RefreshDatabase;

    private Tenant $head;

    private Tenant $west;

    private User $reviewer;

    private User $otherReviewer;

    private User $manager;

    private TenantNoteSubject $subject;

    private Workflow $workflow;

    private TenantNote $note;

    private WorkflowEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        TenantNote::createTable();
        $this->subject = new TenantNoteSubject;
        WorkflowSubjects::register($this->subject);

        $this->head = Tenant::factory()->create(['name' => 'ستاد']);
        $this->west = Tenant::factory()->create(['name' => 'غرب']);

        [$this->reviewer, $this->otherReviewer, $this->manager] = User::factory()->count(3)->create()->all();
        $this->grantRole($this->reviewer, $this->west, 'reviewer');
        $this->grantRole($this->otherReviewer, $this->west, 'reviewer');

        $this->workflow = $this->makeWorkflow($this->head, $this->subject->type(), [
            $this->roleStep('بررسی شعبه', $this->west, 'reviewer'),
            $this->userStep('تأیید ستاد', $this->head, $this->manager),
        ]);

        $this->note = TenantNote::query()->create(['title' => 'اول', 'tenant_id' => $this->head->id]);
        $this->engine = app(WorkflowEngine::class);
    }

    public function test_a_case_starts_at_the_first_step_and_notifies_its_assignees(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);

        $this->assertSame($this->head->id, $case->tenant_id);
        $this->assertSame(CaseStatus::InProgress, $case->status);
        $this->assertSame(0, $case->current_step);
        $this->assertSame($this->west->id, $case->assignee_tenant_id);
        $this->assertSame([CaseActionType::Started], $case->actions()->get()->pluck('type')->all());
        $this->assertSame([['event' => 'moved', 'step' => 0]], $this->subject->events);

        $this->assertSame(1, $this->reviewer->notifications()->count());
        $this->assertSame(1, $this->otherReviewer->notifications()->count());
        $this->assertSame(0, $this->manager->notifications()->count());
    }

    public function test_the_cartable_holds_the_case_only_for_the_step_holders_in_the_steps_tenant(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);

        // Same role, other tenant.
        $headReviewer = User::factory()->create();
        $this->grantRole($headReviewer, $this->head, 'reviewer');

        $this->assertTrue(WorkflowCase::assignedTo($this->reviewer, $this->west)->whereKey($case->id)->exists());
        $this->assertFalse(WorkflowCase::assignedTo($this->reviewer, $this->head)->exists());
        $this->assertFalse(WorkflowCase::assignedTo($headReviewer, $this->head)->exists());
        $this->assertFalse(WorkflowCase::assignedTo($this->manager, $this->head)->exists());
    }

    public function test_approving_moves_through_the_steps_and_the_last_approval_completes_the_case(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);

        $this->engine->approve($case, $this->reviewer, 'خوب است');

        $this->assertSame(1, $case->current_step);
        $this->assertNull($case->assignee_role_id);
        $this->assertSame($this->manager->id, $case->assignee_user_id);
        $this->assertTrue(WorkflowCase::assignedTo($this->manager, $this->head)->exists());
        $this->assertFalse(WorkflowCase::assignedTo($this->reviewer, $this->west)->exists());
        $this->assertSame(1, $this->manager->notifications()->count());

        $this->engine->approve($case, $this->manager, null, ['code' => 'A1']);

        $case->refresh();
        $this->assertSame(CaseStatus::Completed, $case->status);
        $this->assertNull($case->current_step);
        $this->assertNotNull($case->finished_at);
        $this->assertSame(['event' => 'completed', 'data' => ['code' => 'A1']], end($this->subject->events));
        $this->assertSame(
            [CaseActionType::Started, CaseActionType::Approved, CaseActionType::Completed],
            $case->actions()->get()->pluck('type')->all(),
        );
        $this->assertSame('خوب است', $case->actions()->where('type', CaseActionType::Approved)->value('comment'));
    }

    public function test_only_the_current_assignee_can_act(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);

        $this->expectException(WorkflowActionDenied::class);

        $this->engine->approve($case, $this->manager);
    }

    public function test_an_action_on_a_step_that_was_already_left_is_refused(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);
        $staleCopy = WorkflowCase::withoutTenantScope()->find($case->id);

        $this->engine->approve($case, $this->reviewer);

        $this->expectException(WorkflowActionDenied::class);

        $this->engine->approve($staleCopy, $this->otherReviewer);
    }

    public function test_claiming_takes_the_case_out_of_the_other_holders_cartable(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);

        $this->engine->claim($case, $this->reviewer);

        $this->assertSame($this->reviewer->id, $case->claimed_by_id);
        $this->assertTrue(WorkflowCase::assignedTo($this->reviewer, $this->west)->exists());
        $this->assertFalse(WorkflowCase::assignedTo($this->otherReviewer, $this->west)->exists());

        $this->engine->release($case, $this->reviewer);

        $this->assertTrue(WorkflowCase::assignedTo($this->otherReviewer, $this->west)->exists());
    }

    public function test_rejecting_closes_the_case(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);

        $this->engine->reject($case, $this->reviewer, 'ناقص است');

        $this->assertSame(CaseStatus::Rejected, $case->status);
        $this->assertSame(['event' => 'rejected'], end($this->subject->events));
        $this->assertFalse(WorkflowCase::assignedTo($this->reviewer, $this->west)->exists());
        $this->assertSame('ناقص است', $case->actions()->where('type', CaseActionType::Rejected)->value('comment'));
    }

    public function test_actions_not_allowed_in_the_step_are_refused(): void
    {
        $workflow = $this->makeWorkflow($this->head, $this->subject->type(), [
            $this->roleStep('فقط تأیید', $this->west, 'reviewer', actions: []),
        ]);
        $case = $this->engine->start($workflow, $this->note);

        foreach ([
            fn () => $this->engine->reject($case, $this->reviewer, 'نه'),
            fn () => $this->engine->edit($case, $this->reviewer, ['title' => 'دوم']),
        ] as $action) {
            try {
                $action();
                $this->fail('The action should have been refused.');
            } catch (WorkflowActionDenied) {
                $this->assertSame(CaseStatus::InProgress, $case->refresh()->status);
            }
        }
    }

    public function test_a_case_can_be_returned_to_an_earlier_step_only(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);
        $this->engine->approve($case, $this->reviewer);

        $this->engine->returnTo($case, $this->manager, 0, 'مدارک کامل نیست');

        $this->assertSame(0, $case->current_step);
        $this->assertTrue(WorkflowCase::assignedTo($this->otherReviewer, $this->west)->exists());

        $this->expectException(WorkflowActionDenied::class);
        $this->engine->returnTo($case, $this->reviewer, 0, 'همین مرحله');
    }

    public function test_editing_the_subject_records_old_and_new_values(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);

        $this->engine->edit($case, $this->reviewer, ['title' => 'دوم']);

        $this->assertSame('دوم', $this->note->refresh()->title);
        // assertEquals: jsonb does not keep key order.
        $this->assertEquals(
            ['title' => ['label' => 'عنوان', 'old' => 'اول', 'new' => 'دوم']],
            $case->actions()->where('type', CaseActionType::Edited)->sole()->changes,
        );
    }

    public function test_a_failing_completion_undoes_the_approval(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);
        $this->engine->approve($case, $this->reviewer);
        $this->subject->failOnCompletion = true;

        try {
            $this->engine->approve($case, $this->manager);
            $this->fail('The approval should have failed.');
        } catch (WorkflowActionDenied) {
        }

        $case = WorkflowCase::withoutTenantScope()->find($case->id);
        $this->assertSame(CaseStatus::InProgress, $case->status);
        $this->assertSame(1, $case->current_step);
    }

    public function test_running_cases_keep_the_steps_they_started_with(): void
    {
        $case = $this->engine->start($this->workflow, $this->note);

        $this->workflow->update(['steps' => [$this->userStep('تنها مرحله', $this->head, $this->manager)]]);
        $this->engine->approve($case, $this->reviewer);

        $this->assertSame('تأیید ستاد', $case->currentStep()->name);
        $this->assertSame(1, $case->current_step);
    }
}

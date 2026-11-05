<?php

namespace Modules\Campus\Tests\Feature;

use App\Filament\Resources\Workflows\Pages\ViewWorkflow;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Workflow\CaseStatus;
use App\Workflow\StepAction;
use App\Workflow\WorkflowSubjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Campus\Services\WorkflowScene;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fixtures\TenantNoteSubject;
use Tests\TestCase;

/**
 * The 3D view of a workflow (ADR 0064).
 */
class WorkflowSceneTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, RefreshDatabase;

    private Tenant $head;

    private Tenant $west;

    private TenantNoteSubject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Module::find('Campus')?->isEnabled()) {
            $this->markTestSkipped('The Campus module is off.');
        }

        $this->subject = new TenantNoteSubject;
        WorkflowSubjects::register($this->subject);

        $this->head = Tenant::factory()->create(['name' => 'ستاد']);
        $this->west = Tenant::factory()->create(['name' => 'غرب']);
    }

    public function test_steps_stand_in_the_lane_of_their_tenant_with_their_rules(): void
    {
        $person = User::factory()->create(['name' => 'رضا مدیری']);
        $this->grantRole($person, $this->head, 'teacher');

        $scene = app(WorkflowScene::class)->scene($this->workflow($person));

        $this->assertSame(['غرب', 'ستاد'], array_column($scene['lanes'], 'name'));
        $this->assertSame(['1. بررسی شعبه', '3. تأیید شعبه'], $scene['lanes'][0]['steps']);
        $this->assertSame([0, 1, 0], array_column($scene['steps'], 'lane'));
        $this->assertSame([false, true, false], array_column($scene['steps'], 'isPerson'));
        $this->assertSame([true, false, false], array_column($scene['steps'], 'rejects'));
        $this->assertSame([false, true, false], array_column($scene['steps'], 'returns'));
        $this->assertSame('رضا مدیری در ستاد', $scene['steps'][1]['assignee']);
    }

    public function test_each_step_says_what_can_happen_to_a_case(): void
    {
        $person = User::factory()->create();
        $this->grantRole($person, $this->head, 'teacher');

        $steps = app(WorkflowScene::class)->scene($this->workflow($person))['steps'];

        // The first step changes tenant, the second too; the last ends the workflow.
        $this->assertSame([
            ['tone' => 'approve', 'text' => 'با تأیید، پرونده به «تأیید ستاد» در ستاد می‌رود'],
            ['tone' => 'reject', 'text' => 'با رد، پرونده همین‌جا بسته می‌شود'],
        ], $steps[0]['outcomes']);
        $this->assertContains(['tone' => 'return', 'text' => 'می‌تواند پرونده را به هر یک از مرحله‌های قبل برگرداند'], $steps[1]['outcomes']);
        $this->assertStringStartsWith('با تأیید، فرایند تمام می‌شود', $steps[2]['outcomes'][0]['text']);
    }

    public function test_a_first_step_cannot_return_even_if_it_is_allowed(): void
    {
        $workflow = $this->makeWorkflow($this->head, $this->subject->type(), [
            $this->roleStep('بررسی', $this->west, 'reviewer', [StepAction::Return]),
        ]);

        $step = app(WorkflowScene::class)->scene($workflow)['steps'][0];

        $this->assertFalse($step['returns']);
        $this->assertCount(1, $step['outcomes']);
    }

    public function test_open_cases_are_counted_per_step(): void
    {
        $person = User::factory()->create();
        $this->grantRole($person, $this->head, 'teacher');
        $workflow = $this->workflow($person);
        $this->openCases($workflow, step: 1, count: 2);

        $scene = app(WorkflowScene::class)->scene($workflow);

        $this->assertSame([0, 2, 0], array_column($scene['steps'], 'openCases'));
        $this->assertSame(2, $scene['openCases']);
    }

    public function test_a_step_whose_tenant_is_gone_still_draws(): void
    {
        $step = $this->roleStep('بررسی', $this->west, 'reviewer');
        $step['tenant_id'] = $this->west->id + 1000;

        $scene = app(WorkflowScene::class)->scene($this->makeWorkflow($this->head, $this->subject->type(), [$step]));

        $this->assertSame('؟', $scene['lanes'][0]['name']);
    }

    public function test_the_button_opens_the_view_for_who_may_see_the_workflow(): void
    {
        $person = User::factory()->create();
        $this->grantRole($person, $this->head, 'teacher');
        $workflow = $this->workflow($person);
        $this->actingInPanel($this->viewer(), $this->head);

        Livewire::test(ViewWorkflow::class, ['record' => $workflow->getRouteKey()])
            ->assertActionVisible('workflowScene')
            ->mountAction('workflowScene')
            ->assertMountedActionModalSee("نمای سه‌بعدی فرایند «{$workflow->name}»")
            ->assertMountedActionModalSee('بررسی شعبه')
            ->assertMountedActionModalSee('پخش مسیر');
    }

    public function test_a_workflow_without_steps_says_so(): void
    {
        $workflow = $this->makeWorkflow($this->head, $this->subject->type(), []);
        $this->actingInPanel($this->viewer(), $this->head);

        Livewire::test(ViewWorkflow::class, ['record' => $workflow->getRouteKey()])
            ->mountAction('workflowScene')
            ->assertMountedActionModalSee('این فرایند هنوز مرحله‌ای ندارد.');
    }

    private function workflow(User $person): Workflow
    {
        return $this->makeWorkflow($this->head, $this->subject->type(), [
            $this->roleStep('بررسی شعبه', $this->west, 'reviewer', [StepAction::Reject]),
            $this->userStep('تأیید ستاد', $this->head, $person, [StepAction::Return]),
            $this->roleStep('تأیید شعبه', $this->west, 'reviewer', []),
        ]);
    }

    private function viewer(): User
    {
        $this->roleWithPermissions('process_viewer', ['ViewAny:Workflow', 'View:Workflow']);
        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $this->head);
        $this->grantRole($viewer, $this->head, 'process_viewer');

        return $viewer;
    }

    private function openCases(Workflow $workflow, int $step, int $count): void
    {
        foreach (range(1, $count) as $subjectId) {
            WorkflowCase::query()->create([
                'tenant_id' => $this->head->id, 'workflow_id' => $workflow->id, 'workflow_name' => $workflow->name,
                'subject_type' => $this->subject->type(), 'subject_id' => $subjectId, 'steps' => $workflow->steps,
                'status' => CaseStatus::InProgress, 'current_step' => $step,
            ]);
        }
    }
}

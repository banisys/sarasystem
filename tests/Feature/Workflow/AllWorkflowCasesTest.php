<?php

namespace Tests\Feature\Workflow;

use App\Filament\Resources\AllWorkflowCases\AllWorkflowCasesResource;
use App\Filament\Resources\AllWorkflowCases\Pages\ListAllWorkflowCases;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Workflow\CaseStatus;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowSubjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fixtures\TenantNote;
use Tests\Fixtures\TenantNoteSubject;
use Tests\TestCase;

class AllWorkflowCasesTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, RefreshDatabase;

    private Tenant $head;

    private Tenant $west;

    private User $reviewer;

    private User $manager;

    private User $monitor;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        TenantNote::createTable();
        WorkflowSubjects::register(new TenantNoteSubject);

        $this->head = Tenant::factory()->create(['name' => 'ستاد']);
        $this->west = Tenant::factory()->create(['name' => 'غرب']);

        $this->reviewer = User::factory()->create();
        $this->makeStaff($this->reviewer, $this->west);
        $this->grantRole($this->reviewer, $this->west, 'reviewer');

        $this->manager = User::factory()->create(['name' => 'مدیر ستاد']);
        $this->makeStaff($this->manager, $this->head);

        $this->roleWithPermissions('case_monitor', ['ViewAny:WorkflowCase', 'View:WorkflowCase']);
        $this->monitor = User::factory()->create();
        foreach ([$this->head, $this->west] as $tenant) {
            $this->makeStaff($this->monitor, $tenant);
            $this->grantRole($this->monitor, $tenant, 'case_monitor');
        }

        $this->workflow = $this->makeWorkflow($this->head, (new TenantNoteSubject)->type(), [
            $this->roleStep('بررسی شعبه', $this->west, 'reviewer'),
            $this->userStep('تأیید ستاد', $this->head, $this->manager),
        ]);
    }

    public function test_it_lists_the_tenants_cases_in_progress_and_finished_with_who_they_wait_for(): void
    {
        $waiting = $this->startCase('در جریان', $this->head);
        app(WorkflowEngine::class)->approve($waiting, $this->reviewer);
        $done = $this->startCase('تمام‌شده', $this->head);
        app(WorkflowEngine::class)->approve($done, $this->reviewer);
        app(WorkflowEngine::class)->approve($done->refresh(), $this->manager);
        $foreign = $this->startCase('شعبه غرب', $this->west);

        $this->assertSame(CaseStatus::Completed, $done->refresh()->status);

        $this->actingInPanel($this->monitor, $this->head);
        Livewire::test(ListAllWorkflowCases::class)
            ->assertCanSeeTableRecords([$waiting, $done])
            ->assertCanNotSeeTableRecords([$foreign])
            ->assertSee('مدیر ستاد (ستاد)')
            ->filterTable('status', CaseStatus::Completed->value)
            ->assertCanSeeTableRecords([$done])
            ->assertCanNotSeeTableRecords([$waiting]);

        $this->get(AllWorkflowCasesResource::getUrl('view', ['record' => $done], tenant: $this->head))
            ->assertOk()
            ->assertSee('تأیید نهایی')
            ->assertDontSee('تأیید و ارسال به مرحله‌ی بعد');
        $this->get(AllWorkflowCasesResource::getUrl('view', ['record' => $foreign], tenant: $this->head))->assertNotFound();
    }

    public function test_it_needs_the_permission_even_for_assignees(): void
    {
        $case = $this->startCase('در جریان', $this->head);

        $this->actingInPanel($this->reviewer, $this->west);
        $this->get(AllWorkflowCasesResource::getUrl('index', tenant: $this->west))->assertForbidden();

        $this->actingInPanel($this->manager, $this->head);
        $this->get(AllWorkflowCasesResource::getUrl('index', tenant: $this->head))->assertForbidden();
        $this->get(AllWorkflowCasesResource::getUrl('view', ['record' => $case], tenant: $this->head))->assertForbidden();
    }

    private function startCase(string $title, Tenant $tenant): WorkflowCase
    {
        $note = TenantNote::query()->create(['title' => $title, 'tenant_id' => $tenant->id]);

        return app(WorkflowEngine::class)->start($this->workflow, $note);
    }
}

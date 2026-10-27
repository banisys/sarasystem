<?php

namespace Modules\Document\Tests\Feature;

use App\Filament\Resources\WorkflowCases\Pages\ViewWorkflowCase;
use App\Filament\Resources\WorkflowCases\WorkflowCaseResource;
use App\Filament\Resources\Workflows\WorkflowResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowCase;
use App\Workflow\CaseActionType;
use App\Workflow\WorkflowActionDenied;
use App\Workflow\WorkflowEngine;
use App\Workflow\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;
use Modules\Document\Tests\Concerns\MakesDocuments;
use Modules\Document\Workflow\DocumentStepExtension;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Documents to upload and to check in workflow steps (ADR 0019).
 */
class DocumentWorkflowTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, MakesDocuments, RefreshDatabase;

    private Tenant $primary;

    private User $recruiter;

    private User $director;

    private DocumentType $idCard;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->primary = Tenant::factory()->create(['name' => 'ستاد']);
        $this->primary->makePrimary();

        $this->recruiter = User::factory()->create();
        $this->makeStaff($this->recruiter, $this->primary);
        $this->grantRole($this->recruiter, $this->primary, 'recruiter');

        $this->director = User::factory()->create();
        $this->makeStaff($this->director, $this->primary);

        $this->idCard = $this->documentType($this->primary, ['title' => 'کارت ملی']);

        $upload = $this->roleStep('بررسی اولیه', $this->primary, 'recruiter');
        $upload['extensions'] = [DocumentStepExtension::KEY => ['upload' => [$this->idCard->id]]];
        $verify = $this->userStep('تأیید مدارک', $this->primary, $this->director);
        $verify['extensions'] = [DocumentStepExtension::KEY => ['verify' => [$this->idCard->id]]];

        $this->workflow = $this->makeWorkflow($this->primary, (new JobApplication)->getMorphClass(), [$upload, $verify]);
    }

    public function test_steps_keep_their_extension_settings(): void
    {
        $step = WorkflowStep::fromArray($this->workflow->steps[0]);

        $this->assertSame([$this->idCard->id], $step->extension(DocumentStepExtension::KEY)['upload']);
        $this->assertSame($this->workflow->steps[0]['extensions'], $step->toArray()['extensions']);
    }

    public function test_a_step_is_not_approved_until_its_documents_are_uploaded(): void
    {
        $case = $this->startCase();

        try {
            app(WorkflowEngine::class)->approve($case, $this->recruiter);
            $this->fail('Approving should have been refused.');
        } catch (WorkflowActionDenied $exception) {
            $this->assertSame('این مدارک هنوز بارگذاری نشده‌اند: کارت ملی.', $exception->getMessage());
        }

        $this->assertSame(0, $case->refresh()->current_step);
    }

    public function test_the_assignee_uploads_the_steps_documents_in_the_cartable(): void
    {
        $case = $this->startCase();

        $this->actingInPanel($this->recruiter, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->assertActionVisible('uploadDocuments')
            ->assertActionHidden('verifyDocuments')
            ->callAction('uploadDocuments', data: ["{$this->idCard->id}_files" => [self::image()]])
            ->assertHasNoActionErrors()
            ->callAction('approve')
            ->assertHasNoActionErrors();

        $document = Document::withoutTenantScope()->sole();
        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertSame($this->recruiter->id, $document->uploaded_by_id);
        $this->assertCount(1, $document->getMedia(Document::FILES_COLLECTION));
        $this->assertSame(1, $case->refresh()->current_step);
        $this->assertTrue($case->actions()->where('type', CaseActionType::Commented)->where('comment', 'مدارک بارگذاری شد: کارت ملی')->exists());
    }

    public function test_a_document_the_applicant_uploaded_is_enough(): void
    {
        $case = $this->startCase();
        $this->uploadFor($case->subject, $this->idCard);

        app(WorkflowEngine::class)->approve($case, $this->recruiter);

        $this->assertSame(1, $case->refresh()->current_step);
    }

    public function test_a_step_is_not_approved_until_its_documents_are_approved(): void
    {
        $case = $this->startCase();
        $this->uploadFor($case->subject, $this->idCard);
        app(WorkflowEngine::class)->approve($case, $this->recruiter);

        $this->actingInPanel($this->director, $this->primary);
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->assertActionHidden('uploadDocuments')
            ->callAction('approve')
            ->assertNotified('این مدارک هنوز تأیید نشده‌اند: کارت ملی.');
        $this->assertSame(1, $case->refresh()->current_step);

        $field = "{$this->idCard->id}_decision";
        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->mountAction('verifyDocuments')
            ->setActionData([$field => 'reject'])
            ->callMountedAction()
            ->assertHasActionErrors(["{$this->idCard->id}_reason" => 'required'])
            ->setActionData([$field => 'reject', "{$this->idCard->id}_reason" => 'تصویر ناخواناست'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $document = Document::withoutTenantScope()->sole();
        $this->assertSame(DocumentStatus::Rejected, $document->status);
        $this->assertSame('تصویر ناخواناست', $document->rejection_reason);
        $this->assertSame($this->director->id, $document->verified_by_id);

        Livewire::test(ViewWorkflowCase::class, ['record' => $case->getRouteKey()])
            ->callAction('verifyDocuments', data: [$field => 'approve'])
            ->assertHasNoActionErrors();
        $this->assertSame(DocumentStatus::Approved, $document->refresh()->status);

        // The last step hires; the documents move to the new employee.
        app(WorkflowEngine::class)->approve($case->refresh(), $this->director, null, [
            'tenant_id' => $this->primary->id, 'employment_type' => 'full_time', 'hired_at' => '2026-11-01', 'panel_access' => false,
        ]);

        $document->refresh();
        $this->assertNull($case->refresh()->current_step);
        $this->assertSame((new Employee)->getMorphClass(), $document->documentable_type);
        $this->assertNotNull($document->user_id);
    }

    public function test_a_rejected_or_expired_document_counts_as_missing(): void
    {
        $passport = $this->documentType($this->primary, ['title' => 'گواهی سلامت', 'has_expiry' => true]);
        $steps = $this->workflow->steps;
        $steps[0]['extensions'][DocumentStepExtension::KEY]['upload'] = [$passport->id];
        $this->workflow->update(['steps' => $steps]);
        $case = $this->startCase();

        $this->uploadFor($case->subject, $passport, now()->subDay()->toDateString());
        $this->expectException(WorkflowActionDenied::class);

        app(WorkflowEngine::class)->approve($case, $this->recruiter);
    }

    public function test_the_cartable_shows_the_documents_and_the_diagram_names_them(): void
    {
        $this->idCard->update(['required_for' => 'everyone']);
        $case = $this->startCase();

        $this->actingAs($this->recruiter);
        $this->get(WorkflowCaseResource::getUrl('view', ['record' => $case], tenant: $this->primary))
            ->assertOk()
            ->assertSee('کارت ملی')
            ->assertSee('بارگذاری نشده');

        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);
        $this->actingAs($admin)
            ->get(WorkflowResource::getUrl('view', ['record' => $this->workflow], tenant: $this->primary))
            ->assertOk()
            ->assertSee('بارگذاری مدارک: کارت ملی')
            ->assertSee('تأیید مدارک: کارت ملی');
    }

    private function startCase(): WorkflowCase
    {
        return app(WorkflowEngine::class)->start($this->workflow, JobApplication::factory()->for($this->primary)->create());
    }
}

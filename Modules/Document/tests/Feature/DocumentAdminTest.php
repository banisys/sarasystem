<?php

namespace Modules\Document\Tests\Feature;

use App\Documents\ApplicantDocuments;
use App\Documents\DocumentHolders;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Workflow\WorkflowStep;
use Database\Seeders\DemoSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Document\Database\Seeders\DocumentDemoSeeder;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Filament\Resources\Documents\DocumentResource;
use Modules\Document\Filament\Resources\Documents\Pages\ListDocuments;
use Modules\Document\Filament\Resources\DocumentTypes\DocumentTypeResource;
use Modules\Document\Filament\Resources\DocumentTypes\Pages\ListDocumentTypes;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;
use Modules\Document\Tests\Concerns\MakesDocuments;
use Modules\Document\Workflow\DocumentStepExtension;
use Modules\Employee\Database\Seeders\HiringDemoSeeder;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobPosition;
use Modules\Student\Database\Seeders\AdmissionDemoSeeder;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DocumentAdminTest extends TestCase
{
    use InteractsWithTenants, MakesDocuments, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private User $clerk;

    private DocumentType $idCard;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'غرب']);

        $this->roleWithPermissions('hr', ['ViewAny:Document', 'View:Document', 'Delete:Document', 'ViewAny:DocumentType', 'Create:DocumentType', 'Update:DocumentType']);
        $uploader = $this->roleWithPermissions('uploader', []);
        $verifier = $this->roleWithPermissions('verifier', []);

        $this->clerk = User::factory()->create();
        foreach ([$this->primary, $this->west] as $tenant) {
            $this->makeStaff($this->clerk, $tenant);
            $this->grantRole($this->clerk, $tenant, 'hr');
        }
        $this->grantRole($this->clerk, $this->west, 'uploader');

        $this->idCard = $this->documentType($this->primary, ['title' => 'کارت ملی', 'upload_role_ids' => [$uploader->id], 'verify_role_ids' => [$verifier->id]]);
    }

    public function test_types_are_defined_in_the_primary_tenant_only(): void
    {
        $this->actingAs($this->clerk);

        $this->get(DocumentTypeResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(DocumentTypeResource::getUrl('index', tenant: $this->west))->assertForbidden();
    }

    public function test_a_type_is_created_independently_of_forms_and_holder_kinds(): void
    {
        $this->actingInPanel($this->clerk, $this->primary);

        Livewire::test(ListDocumentTypes::class)
            ->callAction('create', data: [
                'title' => 'مدرک تحصیلی', 'file_kind' => 'pdf', 'max_files' => 1, 'max_kilobytes' => 5120, 'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $type = DocumentType::query()->where('title', 'مدرک تحصیلی')->sole();
        $this->assertSame([], app(ApplicantDocuments::class)->fields('employee', null));
        $this->assertSame([], app(ApplicantDocuments::class)->fields('learner', null));
        $this->assertSame('pdf', $type->file_kind->value);
    }

    public function test_the_list_shows_only_the_current_tenants_documents(): void
    {
        $own = $this->uploadFor(Employee::factory()->for($this->west)->create(), $this->idCard);
        $foreign = $this->uploadFor(Employee::factory()->for($this->primary)->create(), $this->idCard);

        $this->actingInPanel($this->clerk, $this->west);

        Livewire::test(ListDocuments::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_upload_roles_upload_and_verify_roles_check(): void
    {
        $employee = Employee::factory()->for($this->west)->create();

        $this->actingInPanel($this->clerk, $this->west);
        Livewire::test(ListDocuments::class)
            ->callAction('upload', data: [
                'holder' => 'employee',
                'record' => DocumentHolders::recordKey($employee),
                'document_type_id' => $this->idCard->id,
                'files' => [self::image()],
            ])
            ->assertHasNoActionErrors();

        $document = Document::withoutTenantScope()->sole();
        $this->assertSame($this->west->id, $document->tenant_id);
        $this->assertSame($employee->user_id, $document->user_id);

        // The clerk may upload but not check.
        Livewire::test(ListDocuments::class)->assertActionHidden(TestAction::make('approve')->table($document));

        $this->grantRole($this->clerk, $this->west, 'verifier');
        $this->actingInPanel($this->clerk, $this->west);
        Livewire::test(ListDocuments::class)
            ->callAction(TestAction::make('approve')->table($document))
            ->assertHasNoActionErrors();

        $this->assertSame(DocumentStatus::Approved, $document->refresh()->status);
    }

    public function test_without_an_upload_role_nothing_can_be_uploaded(): void
    {
        $this->actingInPanel($this->clerk, $this->primary);

        Livewire::test(ListDocuments::class)->assertActionHidden('upload');
    }

    public function test_files_are_shown_through_signed_links_only(): void
    {
        $document = $this->uploadFor(Employee::factory()->for($this->west)->create(), $this->idCard);
        $media = $document->getFirstMedia(Document::FILES_COLLECTION);

        $this->actingAs($this->clerk);
        $this->get($document->fileLinks()[0]['url'])->assertOk();
        $this->get(route('documents.file', ['media' => $media->getKey()]))->assertForbidden();
    }

    public function test_staff_without_permission_cannot_see_documents(): void
    {
        $other = User::factory()->create();
        $this->makeStaff($other, $this->west);

        $this->actingAs($other)->get(DocumentResource::getUrl('index', tenant: $this->west))->assertForbidden();
    }

    public function test_the_demo_seeder_adds_types_and_checks_them_in_the_demo_workflows(): void
    {
        $this->seed(DemoSeeder::class);
        $headOffice = Tenant::query()->where('slug', 'head-office')->sole();
        $headOffice->makePrimary();
        User::factory()->create(['email' => 'admin@sarasystem.test']);
        $this->seed([HiringDemoSeeder::class, AdmissionDemoSeeder::class]);

        $this->seed(DocumentDemoSeeder::class);
        $this->seed(DocumentDemoSeeder::class);

        $this->assertSame(5, DocumentType::withoutTenantScope()->where('tenant_id', $headOffice->id)->count());
        $hiring = Workflow::withoutTenantScope()->where('name', HiringDemoSeeder::WORKFLOW_NAME)->sole();
        $this->assertCount(3, WorkflowStep::fromArray($hiring->steps[0])->extension(DocumentStepExtension::KEY)['verify']);
        $degree = DocumentType::withoutTenantScope()->where('title', 'آخرین مدرک تحصیلی')->sole();
        foreach (JobPosition::withoutTenantScope()->where('tenant_id', $headOffice->id)->where('title', 'like', 'معلم%')->get() as $position) {
            $this->assertContains($degree->id, array_column(app(ApplicantDocuments::class)->fields('employee', $position->id), 'id'));
        }
    }
}

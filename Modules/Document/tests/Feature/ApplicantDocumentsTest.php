<?php

namespace Modules\Document\Tests\Feature;

use App\Documents\ApplicantDocuments;
use App\Filament\CustomFields\CustomizeFormAction;
use App\Models\Tenant;
use App\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;
use Modules\Document\Services\DocumentFormFields;
use Modules\Document\Tests\Concerns\MakesDocuments;
use Modules\Employee\Forms\JobApplicationForm;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Modules\Employee\Tests\Concerns\SubmitsJobApplications;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * Applicants upload documents in «همکاری با ما» and «پیش‌ثبت‌نام» (ADR 0019).
 */
class ApplicantDocumentsTest extends TestCase
{
    use MakesDocuments, RefreshDatabase, SubmitsJobApplications;

    private Tenant $primary;

    private JobPosition $position;

    private FakeSmsSender $sms;

    private DocumentType $idCard;

    private DocumentType $degree;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->position = JobPosition::factory()->for($this->primary)->create(['title' => 'معلم ریاضی']);

        $this->idCard = DocumentType::factory()->for($this->primary)->create(['title' => 'کارت ملی']);
        // Required for the math teacher only.
        $this->degree = DocumentType::factory()->for($this->primary)->create([
            'title' => 'مدرک تحصیلی', 'file_kind' => 'pdf', 'max_files' => 1,
        ]);
        $this->primary->execute(function (): void {
            CustomizeFormAction::save(new JobApplicationForm, null, ['field_modes' => [DocumentFormFields::key($this->idCard->id) => 'required']]);
            CustomizeFormAction::save(new JobApplicationForm, $this->position, ['field_modes' => [DocumentFormFields::key($this->degree->id) => 'required']]);
            CustomizeFormAction::save(new PreregistrationForm, null, ['field_modes' => [DocumentFormFields::key($this->idCard->id) => 'required']]);
        });
        // Staff upload it; not asked in the form.
        DocumentType::factory()->for($this->primary)->create(['title' => 'گواهی عدم سوءپیشینه']);
    }

    public function test_the_form_asks_each_positions_documents(): void
    {
        $janitor = JobPosition::factory()->for($this->primary)->create(['title' => 'سرایدار']);

        $titles = fn (array $page, int $positionId): array => collect(collect($page['props']['positions'])->firstWhere('id', $positionId)['documents'])->pluck('title')->sort()->values()->all();
        $page = [];
        $this->get('/careers')->assertInertia(function (AssertableInertia $inertia) use (&$page): void {
            $page = $inertia->toArray();
        });

        $this->assertSame(collect(['کارت ملی', 'مدرک تحصیلی'])->sort()->values()->all(), $titles($page, $this->position->id));
        $this->assertSame(['کارت ملی'], $titles($page, $janitor->id));
        $this->assertSame([['title' => 'کارت ملی', 'required' => true]], collect($page['props']['documents'])->map(fn (array $field): array => ['title' => $field['title'], 'required' => $field['required']])->all());
    }

    public function test_required_documents_must_be_sent_and_are_stored_for_review(): void
    {
        $this->verifyMobile();
        $this->post('/careers', $this->validData())->assertSessionHasErrors(["documents.{$this->idCard->id}", "documents.{$this->degree->id}"]);
        $this->post('/careers', $this->validData(['documents' => [$this->degree->id => [self::image('degree.jpg')]]]))
            ->assertSessionHasErrors("documents.{$this->degree->id}.0");

        $this->post('/careers', $this->validData(['documents' => [
            $this->idCard->id => [self::image('front.jpg'), self::image('back.jpg')],
            $this->degree->id => [UploadedFile::fake()->create('degree.pdf', 100, 'application/pdf')],
        ]]))->assertSessionHasNoErrors();

        $application = JobApplication::withoutTenantScope()->sole();
        $documents = Document::withoutTenantScope()->of($application)->get()->keyBy('document_type_id');
        $this->assertCount(2, $documents);
        $this->assertSame(DocumentStatus::Pending, $documents[$this->idCard->id]->status);
        $this->assertNull($documents[$this->idCard->id]->uploaded_by_id);
        $this->assertCount(2, $documents[$this->idCard->id]->getMedia(Document::FILES_COLLECTION));
        $this->assertSame('local', $documents[$this->idCard->id]->getFirstMedia(Document::FILES_COLLECTION)->disk);
    }

    public function test_resending_the_form_need_not_send_the_documents_again(): void
    {
        $this->verifyMobile();
        $this->post('/careers', $this->validData(['national_code' => '0013542419', 'documents' => [
            $this->idCard->id => [self::image()],
            $this->degree->id => [UploadedFile::fake()->create('degree.pdf', 100, 'application/pdf')],
        ]]))->assertSessionHasNoErrors();

        $this->verifyMobile();
        $this->post('/careers', $this->validData(['national_code' => '0013542419', 'first_name' => 'مریم']))->assertSessionHasNoErrors();

        $this->assertSame(2, Document::withoutTenantScope()->count());
    }

    public function test_new_types_are_not_asked_until_selected_and_foreign_types_cannot_be_uploaded(): void
    {
        $hidden = DocumentType::factory()->for($this->primary)->create();
        $foreign = DocumentType::factory()->create();
        $fields = app(ApplicantDocuments::class)->fields('employee', $this->position->id);
        $this->assertNotContains($hidden->id, array_column($fields, 'id'));
        $this->assertNotContains($foreign->id, array_column($fields, 'id'));

        $this->verifyMobile();
        $this->post('/careers', $this->validData(['documents' => [
            $this->idCard->id => [self::image()],
            $this->degree->id => [UploadedFile::fake()->create('degree.pdf', 100, 'application/pdf')],
            $hidden->id => [self::image()],
            $foreign->id => [self::image()],
        ]]))->assertSessionHasErrors('documents');

        $this->assertSame(0, Document::withoutTenantScope()->count());
        $this->assertSame(0, JobApplication::withoutTenantScope()->count());
    }

    public function test_each_program_overrides_the_general_form_without_changing_other_forms(): void
    {
        $program = Program::factory()->for($this->primary)->create();
        $other = Program::factory()->for($this->primary)->create();
        $key = DocumentFormFields::key($this->idCard->id);
        $this->primary->execute(fn () => CustomizeFormAction::save(new PreregistrationForm, $program, ['field_modes' => [$key => 'hidden', DocumentFormFields::key($this->degree->id) => 'optional']]));
        $documents = app(ApplicantDocuments::class);

        $this->assertSame([['id' => $this->degree->id, 'required' => false]], array_map(fn (array $field): array => ['id' => $field['id'], 'required' => $field['required']], $documents->fields('learner', $program->id)));
        $this->assertSame([$this->idCard->id], array_column($documents->fields('learner', $other->id), 'id'));
        $this->assertCount(2, $documents->fields('employee', $this->position->id));
        $foreignProgram = Program::factory()->create();
        $this->assertSame([], $documents->fields('learner', $foreignProgram->id));
    }

    public function test_preregistration_asks_the_learners_documents(): void
    {
        $program = Program::factory()->for($this->primary)->create();
        $this->post('/preregister/mobile/code', ['mobile' => '09121234567']);
        $this->post('/preregister/mobile/verify', ['mobile' => '09121234567', 'code' => $this->sms->lastCodeFor('09121234567')]);

        $this->get('/preregister')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('programs.0.documents', 1)
            ->where('programs.0.documents.0.title', 'کارت ملی'));

        $this->post('/preregister', [
            'program_id' => $program->id, 'first_name' => 'امیر', 'last_name' => 'محمدی', 'nationality' => 'iranian',
            'national_code' => '0013542419', 'birth_date' => '1393/02/10', 'gender' => 'male', 'mobile' => '09121234567',
            'documents' => [$this->idCard->id => [self::image()]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('learner', Document::withoutTenantScope()->of(Preregistration::withoutTenantScope()->sole())->sole()->holder);
    }
}

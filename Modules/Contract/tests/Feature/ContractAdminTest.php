<?php

namespace Modules\Contract\Tests\Feature;

use App\Models\FormCustomization;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Enums\SignatureMethod;
use Modules\Contract\Enums\SignedVia;
use Modules\Contract\Filament\Resources\Contracts\ContractResource;
use Modules\Contract\Filament\Resources\Contracts\Pages\ListContracts;
use Modules\Contract\Filament\Resources\ContractTypes\ContractTypeResource;
use Modules\Contract\Filament\Resources\ContractTypes\Pages\ListContractTypes;
use Modules\Contract\Forms\ContractForm;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Services\ContractException;
use Modules\Contract\Services\ContractRenderer;
use Modules\Contract\Services\Contracts;
use Modules\Contract\Tests\Concerns\MakesContracts;
use Modules\Employee\Models\JobApplication;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * Contract types and contracts in the admin panel (ADR 0020).
 */
class ContractAdminTest extends TestCase
{
    use InteractsWithTenants, MakesContracts, RefreshDatabase;

    private Tenant $primary;

    private Tenant $branch;

    private User $admin;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create(['name' => 'ستاد']);
        $this->primary->makePrimary();
        $this->branch = Tenant::factory()->create(['name' => 'شعبه شمال']);

        $this->admin = User::factory()->create();
        $this->admin->makeSuperAdmin($this->primary);
    }

    public function test_types_are_managed_in_the_primary_tenant_only(): void
    {
        $this->actingAs($this->admin);

        $this->get(ContractTypeResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(ContractTypeResource::getUrl('index', tenant: $this->branch))->assertForbidden();
    }

    public function test_a_type_is_created_with_its_text(): void
    {
        $this->actingInPanel($this->admin, $this->primary);

        Livewire::test(ListContractTypes::class)
            ->callAction('create', data: [
                'title' => 'قرارداد کارمند',
                'parties' => ['employee'],
                'signature_method' => SignatureMethod::Paper->value,
                'number_prefix' => 'K-',
                'body' => '<p>طرف قرارداد: <span data-type="mergeTag" data-id="person_name"></span></p>',
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $type = ContractType::query()->sole();
        $this->assertSame($this->primary->id, $type->tenant_id);
        $this->assertSame(['employee'], $type->parties);
        $this->assertSame(SignatureMethod::Paper, $type->signature_method);
        $this->assertStringContainsString('data-id="person_name"', $type->body);
    }

    public function test_merge_tags_include_the_parties_tags_and_the_types_added_fields(): void
    {
        $type = $this->contractType($this->primary, ['parties' => ['employee']]);
        $this->addField($type, 'fwage', 'حقوق ماهانه');

        $tags = $type->refresh()->mergeTags();

        $this->assertSame('نام و نام خانوادگی', $tags['person_name']);
        $this->assertSame('عنوان شغل', $tags['job_title']);
        $this->assertArrayNotHasKey('program', $tags);
        $this->assertSame('حقوق ماهانه', $tags['fwage']);
    }

    public function test_staff_prepare_a_draft_for_a_member_of_their_tenant(): void
    {
        $type = $this->contractType($this->primary, ['parties' => ['employee']]);
        $employee = $this->employeeIn($this->branch);

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)
            ->callAction('prepare', data: [
                'party' => 'employee',
                'member' => $employee->id,
                'contract_type_id' => $type->id,
                'starts_on' => '2026-11-01',
                'ends_on' => '2027-10-31',
            ])
            ->assertHasNoActionErrors();

        $contract = Contract::withoutTenantScope()->sole();
        $this->assertSame(ContractStatus::Draft, $contract->status);
        $this->assertSame($this->branch->id, $contract->tenant_id);
        $this->assertSame($employee->user_id, $contract->user_id);
        $this->assertSame('2027-10-31', $contract->ends_on->toDateString());
        $this->assertNull($contract->number);
    }

    public function test_issuing_fixes_the_text_numbers_it_and_tells_the_person(): void
    {
        $type = $this->contractType($this->primary, ['parties' => ['employee'], 'number_prefix' => 'K-']);
        $this->addField($type, 'fwage', 'حقوق ماهانه');
        $type->update(['body' => '<p><span data-type="mergeTag" data-id="person_name"></span> — <span data-type="mergeTag" data-id="tenant_name"></span> — <span data-type="mergeTag" data-id="amount_words"></span> — <span data-type="mergeTag" data-id="fwage"></span> — <span data-type="mergeTag" data-id="father_name"></span></p>']);
        $employee = $this->employeeIn($this->branch, User::factory()->create(['name' => 'سارا محمدی', 'mobile' => '09121234567']));
        $contract = $this->draftFor($employee, $type->refresh(), ['starts_on' => '2026-11-01', 'amount' => 1250000, 'custom' => ['fwage' => '۲۰۰ میلیون']]);

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)
            ->callTableAction('issue', $contract)
            ->assertHasNoTableActionErrors();

        $contract->refresh();
        $this->assertSame(ContractStatus::Issued, $contract->status);
        $this->assertSame('K-1', $contract->number);
        $this->assertStringContainsString('سارا محمدی — شعبه شمال — یک میلیون و دویست و پنجاه هزار ریال — ۲۰۰ میلیون — '.ContractRenderer::BLANK, strip_tags($contract->body));
        $this->assertSame(hash('sha256', $contract->body), $contract->body_hash);
        $this->assertSame($this->admin->id, $contract->issued_by_id);
        $this->assertSame('09121234567', $this->sms->sent[0]['mobile']);
        $this->assertStringContainsString($type->title, $this->sms->sent[0]['message']);

        // Later changes to the type don't change the issued text.
        $type->update(['body' => '<p>متن تازه</p>']);
        $this->assertStringContainsString('سارا محمدی', app(ContractRenderer::class)->body($contract->refresh()));

        $this->assertSame('K-2', $this->issuedFor($this->employeeIn($this->branch), $type)->number);
    }

    public function test_no_sms_for_a_type_signed_on_paper(): void
    {
        $type = $this->contractType($this->primary, ['signature_method' => SignatureMethod::Paper]);

        $this->issuedFor($this->employeeIn($this->branch), $type);

        $this->assertSame([], $this->sms->sent);
    }

    public function test_only_drafts_change_and_an_applicants_draft_waits_for_acceptance(): void
    {
        $type = $this->contractType($this->primary);
        $issued = $this->issuedFor($this->employeeIn($this->branch), $type);

        try {
            app(Contracts::class)->saveDraft($issued, $type, $issued->contractable, ['starts_on' => '2026-12-01'], null);
            $this->fail('An issued contract should not change.');
        } catch (ContractException $exception) {
            $this->assertSame('فقط پیش‌نویس قرارداد را می‌توان تغییر داد.', $exception->getMessage());
        }

        $applicantDraft = $this->draftFor(JobApplication::factory()->for($this->primary)->create(), $type);
        $this->expectExceptionMessage('قرارداد متقاضی پس از پذیرش او صادر می‌شود.');
        app(Contracts::class)->issue($applicantDraft, null);
    }

    public function test_a_paper_signature_is_recorded_with_a_scan(): void
    {
        Storage::fake('local');
        $type = $this->contractType($this->primary, ['signature_method' => SignatureMethod::Either]);
        $contract = $this->issuedFor($this->employeeIn($this->branch), $type);

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)
            ->assertTableActionVisible('paperSignature', $contract)
            ->callTableAction('paperSignature', $contract, data: ['scan' => UploadedFile::fake()->create('signed.pdf', 200, 'application/pdf')])
            ->assertHasNoTableActionErrors();

        $contract->refresh();
        $this->assertSame(ContractStatus::Signed, $contract->status);
        $this->assertSame(SignedVia::Paper, $contract->signed_via);
        $this->assertSame($this->admin->id, $contract->signature_recorded_by_id);
        $this->assertTrue($contract->hasMedia(Contract::SCAN_COLLECTION));
        $this->assertStringContainsString('امضای کاغذی', app(ContractRenderer::class)->signatureBlock($contract));
    }

    public function test_no_paper_signature_for_an_electronic_only_type(): void
    {
        $type = $this->contractType($this->primary, ['signature_method' => SignatureMethod::Electronic]);
        $contract = $this->issuedFor($this->employeeIn($this->branch), $type);

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)->assertTableActionHidden('paperSignature', $contract);
    }

    public function test_an_issued_contract_is_cancelled_with_a_reason(): void
    {
        $contract = $this->issuedFor($this->employeeIn($this->branch), $this->contractType($this->primary));

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)
            ->mountTableAction('cancel', $contract)
            ->setTableActionData(['reason' => ''])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['reason' => 'required'])
            ->setTableActionData(['reason' => 'اشتباه در مبلغ'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $contract->refresh();
        $this->assertSame(ContractStatus::Cancelled, $contract->status);
        $this->assertSame('اشتباه در مبلغ', $contract->cancellation_reason);
    }

    public function test_the_pdf_is_made(): void
    {
        $draft = $this->draftFor($this->employeeIn($this->branch), $this->contractType($this->primary));

        $pdf = app(ContractRenderer::class)->pdf($draft);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('پیش‌نویس', app(ContractRenderer::class)->document($draft));
    }

    public function test_another_tenants_contracts_are_not_listed(): void
    {
        $type = $this->contractType($this->primary);
        $mine = $this->draftFor($this->employeeIn($this->branch), $type);
        $theirs = $this->draftFor($this->employeeIn($this->primary), $type);

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_staff_need_contract_permissions(): void
    {
        $clerk = User::factory()->create();
        $this->makeStaff($clerk, $this->branch);

        $this->actingAs($clerk)->get(ContractResource::getUrl('index', tenant: $this->branch))->assertForbidden();

        $this->roleWithPermissions('contracts_clerk', ['ViewAny:Contract']);
        $this->grantRole($clerk, $this->branch, 'contracts_clerk');
        $contract = $this->draftFor($this->employeeIn($this->branch), $this->contractType($this->primary));

        $this->actingInPanel($clerk, $this->branch);
        Livewire::test(ListContracts::class)
            ->assertActionHidden('prepare')
            ->assertTableActionHidden('issue', $contract)
            ->assertTableActionVisible('preview', $contract);
    }

    private function addField(ContractType $type, string $key, string $label): void
    {
        FormCustomization::withoutTenantScope()->create([
            'tenant_id' => $this->primary->id,
            'form' => ContractForm::KEY,
            'owner_type' => $type->getMorphClass(),
            'owner_id' => $type->id,
            'field_modes' => [],
            'fields' => [['key' => $key, 'label' => $label, 'type' => 'text', 'section' => 'terms']],
        ]);
    }
}

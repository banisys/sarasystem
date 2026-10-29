<?php

namespace Modules\Contract\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Enums\SignatureMethod;
use Modules\Contract\Enums\SignedVia;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Tests\Concerns\MakesContracts;
use Modules\Employee\Models\Employee;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * The person's contracts in the user panel and signing with an SMS code
 * (ADR 0020).
 */
class SignContractTest extends TestCase
{
    use InteractsWithTenants, MakesContracts, RefreshDatabase;

    private const MOBILE = '09121234567';

    private Tenant $primary;

    private Tenant $branch;

    private User $person;

    private Employee $employee;

    private ContractType $type;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->branch = Tenant::factory()->create();

        $this->person = User::factory()->create(['mobile' => self::MOBILE]);
        $this->grantRole($this->person, $this->branch, 'employee');
        $this->employee = $this->employeeIn($this->branch, $this->person);
        $this->type = $this->contractType($this->primary, ['title' => 'قرارداد کارمند', 'signature_method' => SignatureMethod::Electronic]);
    }

    public function test_the_person_sees_their_issued_contracts_only(): void
    {
        $issued = $this->issuedFor($this->employee, $this->type);
        $this->draftFor($this->employee, $this->type);
        $this->issuedFor($this->employeeIn($this->branch), $this->type);

        $this->visit(route('contracts.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Contract::Index')
            ->has('contracts', 1)
            ->where('contracts.0.id', $issued->id)
            ->where('contracts.0.status_label', 'در انتظار امضا'));
    }

    public function test_drafts_and_other_peoples_contracts_are_not_found(): void
    {
        $draft = $this->draftFor($this->employee, $this->type);
        $someoneElses = $this->issuedFor($this->employeeIn($this->branch), $this->type);

        $this->visit(route('contracts.show', $draft))->assertNotFound();
        $this->visit(route('contracts.show', $someoneElses))->assertNotFound();
        $this->visit(route('contracts.pdf', $someoneElses))->assertNotFound();
    }

    public function test_another_tenants_contract_is_not_found_from_this_tenant(): void
    {
        $this->grantRole($this->person, $this->primary, 'employee');
        $elsewhere = $this->issuedFor($this->employeeIn($this->primary, $this->person), $this->type);

        $this->visit(route('contracts.show', $elsewhere))->assertNotFound();
        $this->visit(route('contracts.show', $elsewhere), $this->primary)->assertOk();
    }

    public function test_the_person_signs_with_an_sms_code(): void
    {
        $contract = $this->issuedFor($this->employee, $this->type);

        $this->visit(route('contracts.show', $contract))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Contract::Show')
            ->where('contract.can_sign', true)
            ->where('contract.html', fn (string $html): bool => str_contains($html, $this->person->name)));

        $this->visit(route('contracts.code', $contract), method: 'post')->assertSessionHasNoErrors();
        $code = $this->sms->lastCodeFor(self::MOBILE);
        $this->assertStringContainsString('کد امضای شما', end($this->sms->sent)['message']);

        $this->visit(route('contracts.sign', $contract), method: 'post', data: ['code' => $code])
            ->assertSessionHasErrors('accept');
        $this->visit(route('contracts.sign', $contract), method: 'post', data: ['code' => '000000', 'accept' => true])
            ->assertSessionHasErrors('code');
        $this->visit(route('contracts.sign', $contract), method: 'post', data: ['code' => $code, 'accept' => true])
            ->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame(ContractStatus::Signed, $contract->status);
        $this->assertSame(SignedVia::Electronic, $contract->signed_via);
        $this->assertSame(self::MOBILE, $contract->signed_mobile);
        $this->assertNotNull($contract->signed_ip);
        $this->assertNotNull($contract->signed_at);
    }

    public function test_a_code_signs_only_its_own_contract(): void
    {
        $first = $this->issuedFor($this->employee, $this->type);
        $second = $this->issuedFor($this->employee, $this->contractType($this->primary));

        $this->visit(route('contracts.code', $first), method: 'post');
        $code = $this->sms->lastCodeFor(self::MOBILE);

        $this->visit(route('contracts.sign', $second), method: 'post', data: ['code' => $code, 'accept' => true])
            ->assertSessionHasErrors('code');
        $this->assertSame(ContractStatus::Issued, $second->refresh()->status);
    }

    public function test_a_paper_only_contract_is_not_signed_in_the_panel(): void
    {
        $contract = $this->issuedFor($this->employee, $this->contractType($this->primary, ['signature_method' => SignatureMethod::Paper]));

        $this->visit(route('contracts.show', $contract))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contract.can_sign', false)
            ->where('contract.signs_on_paper', true));
        $this->visit(route('contracts.code', $contract), method: 'post')->assertSessionHasErrors('code');
        $this->assertSame([], $this->sms->sent);
    }

    public function test_the_person_downloads_the_pdf(): void
    {
        $contract = $this->issuedFor($this->employee, $this->type);

        $response = $this->visit(route('contracts.pdf', $contract))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_header_links_to_contracts_once_there_is_one(): void
    {
        $this->visit(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('navigation', fn ($items): bool => ! collect($items)->contains('title', 'قراردادها')));

        $this->issuedFor($this->employee, $this->type);

        $this->visit(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('navigation', fn ($items): bool => collect($items)->contains('title', 'قراردادها')));
    }

    /**
     * A request as the person, with the tenant chosen on the web side.
     *
     * @param  array<string, mixed>  $data
     */
    private function visit(string $url, ?Tenant $tenant = null, string $method = 'get', array $data = []): TestResponse
    {
        return $this->actingAs($this->person)
            ->withSession([ResolveCurrentTenant::SESSION_KEY => ($tenant ?? $this->branch)->id])
            ->{$method}($url, $data);
    }
}

<?php

namespace Modules\Contract\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Contract\Enums\ContractKind;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Filament\Resources\Contracts\Pages\ListContracts;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Services\ContractException;
use Modules\Contract\Services\ContractRenderer;
use Modules\Contract\Services\Contracts;
use Modules\Contract\Tests\Concerns\MakesContracts;
use Modules\Employee\Models\Employee;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * Amendments and renewals of signed contracts (ADR 0021).
 */
class AmendmentRenewalTest extends TestCase
{
    use InteractsWithTenants, MakesContracts, RefreshDatabase;

    private Tenant $primary;

    private Tenant $branch;

    private User $admin;

    private ContractType $employment;

    private ContractType $raise;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsSender::class, new FakeSmsSender);

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->branch = Tenant::factory()->create();

        $this->admin = User::factory()->create();
        $this->admin->makeSuperAdmin($this->primary);

        $this->employment = $this->contractType($this->primary, ['title' => 'قرارداد کارمند', 'parties' => ['employee'], 'number_prefix' => 'K-']);
        $this->raise = $this->contractType($this->primary, [
            'title' => 'الحاقیه افزایش حقوق',
            'parties' => ['employee'],
            'is_amendment' => true,
            'body' => '<p>به قرارداد <span data-type="mergeTag" data-id="parent_number"></span> از <span data-type="mergeTag" data-id="starts_on"></span> افزوده می‌شود.</p>',
        ]);
        $this->employee = $this->employeeIn($this->branch);
    }

    public function test_a_signed_contract_gets_numbered_amendments(): void
    {
        $contract = $this->signed();

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)
            ->assertTableActionVisible('amend', $contract)
            ->callTableAction('amend', $contract, data: ['contract_type_id' => $this->raise->id, 'starts_on' => '2027-03-21'])
            ->assertHasNoTableActionErrors();

        $amendment = Contract::withoutTenantScope()->where('kind', ContractKind::Amendment)->sole();
        $this->assertSame($contract->id, $amendment->parent_id);
        $this->assertTrue($amendment->contractable->is($this->employee));
        $this->assertSame($this->branch->id, $amendment->tenant_id);
        $this->assertSame($this->employee->user_id, $amendment->user_id);

        app(Contracts::class)->issue($amendment, null);
        $this->assertSame('K-1/1', $amendment->refresh()->number);
        $this->assertSame('به قرارداد K-1 از 1406/01/01 افزوده می‌شود.', strip_tags($amendment->body));

        $second = app(Contracts::class)->issue(app(Contracts::class)->draftAmendment($contract, $this->raise, ['starts_on' => '2027-09-23'], null), null);
        $this->assertSame('K-1/2', $second->number);

        // The type's own numbering is untouched by amendments.
        $this->assertSame('K-2', $this->issuedFor($this->employeeIn($this->branch), $this->employment)->number);
    }

    public function test_only_signed_contracts_of_their_own_are_amended(): void
    {
        $issued = $this->issuedFor($this->employee, $this->employment);

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)->assertTableActionHidden('amend', $issued);

        $this->expectException(ContractException::class);
        app(Contracts::class)->draftAmendment($issued, $this->raise, ['starts_on' => '2027-03-21'], null);
    }

    public function test_amendment_types_are_not_used_for_contracts_of_their_own(): void
    {
        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)
            ->mountAction('prepare')
            ->setActionData(['party' => 'employee'])
            ->assertFormFieldExists('contract_type_id', 'mountedActionSchema0', fn ($field): bool => array_keys($field->getOptions()) === [$this->employment->id]);

        $this->expectExceptionMessage('الحاقیه است');
        app(Contracts::class)->saveDraft(null, $this->raise, $this->employee, ['starts_on' => '2027-03-21'], null);
    }

    public function test_a_renewal_starts_after_the_contract_and_keeps_its_terms(): void
    {
        $contract = $this->signed(['starts_on' => '2026-09-23', 'ends_on' => '2027-06-21', 'amount' => 500]);

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ListContracts::class)
            ->mountTableAction('renew', $contract)
            ->assertTableActionDataSet([
                'contract_type_id' => $this->employment->id,
                'starts_on' => '2027-06-22',
                'ends_on' => '2028-03-19',
                'amount' => 500,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $renewal = Contract::withoutTenantScope()->where('kind', ContractKind::Renewal)->sole();
        $this->assertSame($contract->id, $renewal->parent_id);
        $this->assertSame(ContractStatus::Draft, $renewal->status);
        $this->assertSame('2027-06-22', $renewal->starts_on->toDateString());
        $this->assertSame(500, $renewal->amount);

        app(Contracts::class)->issue($renewal, null);
        $this->assertSame('K-2', $renewal->refresh()->number);
        $this->assertStringContainsString('در ادامه‌ی قرارداد K-1', app(ContractRenderer::class)->document($renewal));
    }

    public function test_a_contract_is_renewed_once_unless_the_renewal_is_cancelled(): void
    {
        $contract = $this->signed();
        $renewal = app(Contracts::class)->draftRenewal($contract, $this->employment, ['starts_on' => '2027-11-01'], null);

        $this->assertFalse($contract->refresh()->canBeRenewed());

        try {
            app(Contracts::class)->draftRenewal($contract, $this->employment, ['starts_on' => '2027-11-01'], null);
            $this->fail('A second renewal should be refused.');
        } catch (ContractException) {
        }

        app(Contracts::class)->cancel(app(Contracts::class)->issue($renewal, null), $this->admin, 'اشتباه');
        $this->assertTrue($contract->refresh()->canBeRenewed());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function signed(array $state = ['starts_on' => '2026-11-01', 'ends_on' => '2027-10-31']): Contract
    {
        $contract = app(Contracts::class)->issue($this->draftFor($this->employee, $this->employment, $state), null);

        return app(Contracts::class)->signElectronically($contract, $this->employee->user, '127.0.0.1');
    }
}

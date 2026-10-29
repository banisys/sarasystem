<?php

namespace Modules\Contract\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Filament\RelationManagers\ContractsRelationManager;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Tests\Concerns\MakesContracts;
use Modules\Document\Filament\RelationManagers\DocumentsRelationManager;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;
use Modules\Employee\Filament\Resources\Employees\Pages\ViewEmployee;
use Modules\Employee\Models\Employee;
use Modules\Student\Filament\Resources\Students\StudentResource;
use Modules\Student\Models\Student;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * The «قراردادها» tab on employees' and learners' pages (ADR 0020).
 */
class ContractsTabTest extends TestCase
{
    use InteractsWithTenants, MakesContracts, RefreshDatabase;

    private Tenant $primary;

    private Tenant $branch;

    private User $admin;

    private ContractType $type;

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

        $this->type = $this->contractType($this->primary, ['title' => 'قرارداد کارمند', 'parties' => ['employee']]);
        $this->employee = $this->employeeIn($this->branch, User::factory()->create(['name' => 'سارا محمدی']));
    }

    public function test_employee_and_learner_pages_have_contracts_and_documents_tabs(): void
    {
        $this->assertContains(ContractsRelationManager::class, EmployeeResource::getRelations());
        $this->assertContains(DocumentsRelationManager::class, EmployeeResource::getRelations());
        $this->assertContains(ContractsRelationManager::class, StudentResource::getRelations());

        $student = Student::factory()->for($this->branch)->create();

        $this->actingAs($this->admin);
        $this->get(EmployeeResource::getUrl('view', ['record' => $this->employee], tenant: $this->branch))
            ->assertOk()
            ->assertSee('سارا محمدی')
            ->assertSee('اطلاعات')
            ->assertSee('قراردادها')
            ->assertSee('مدارک');
        $this->get(StudentResource::getUrl('view', ['record' => $student], tenant: $this->branch))
            ->assertOk()
            ->assertSee('قراردادها');
    }

    public function test_the_tab_lists_the_persons_contracts_and_prepares_a_new_one(): void
    {
        $mine = $this->draftFor($this->employee, $this->type);
        $someoneElses = $this->draftFor($this->employeeIn($this->branch), $this->type);
        $amendmentType = $this->contractType($this->primary, ['title' => 'الحاقیه', 'parties' => ['employee'], 'is_amendment' => true]);

        $this->actingInPanel($this->admin, $this->branch);
        Livewire::test(ContractsRelationManager::class, ['ownerRecord' => $this->employee, 'pageClass' => ViewEmployee::class])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$someoneElses])
            ->assertTableActionVisible('issue', $mine)
            ->mountAction(TestAction::make('prepare')->table())
            ->assertFormFieldExists('contract_type_id', 'mountedActionSchema0', fn ($field): bool => array_keys($field->getOptions()) === [$this->type->id] && ! isset($field->getOptions()[$amendmentType->id]))
            ->setActionData(['contract_type_id' => $this->type->id, 'starts_on' => '2026-12-01'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $new = Contract::withoutTenantScope()->latest('id')->first();
        $this->assertTrue($new->contractable->is($this->employee));
        $this->assertSame(ContractStatus::Draft, $new->status);
        $this->assertSame('2026-12-01', $new->starts_on->toDateString());
    }

    public function test_the_tab_follows_contract_permissions(): void
    {
        $clerk = User::factory()->create();
        $this->makeStaff($clerk, $this->branch);
        $this->roleWithPermissions('employees_clerk', ['ViewAny:Employee', 'View:Employee']);
        $this->grantRole($clerk, $this->branch, 'employees_clerk');

        $this->actingInPanel($clerk, $this->branch);
        $this->assertFalse(ContractsRelationManager::canViewForRecord($this->employee, ViewEmployee::class));

        $this->roleWithPermissions('employees_clerk', ['ViewAny:Contract']);
        $clerk->unsetRelation('roles')->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue(ContractsRelationManager::canViewForRecord($this->employee, ViewEmployee::class));
    }
}

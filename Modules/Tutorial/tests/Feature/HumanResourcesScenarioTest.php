<?php

namespace Modules\Tutorial\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowCase;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Contract\Enums\ContractKind;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Enums\SignedVia;
use Modules\Contract\Models\Contract;
use Modules\Document\Models\Document;
use Modules\Employee\Models\Employee;
use Modules\Tutorial\Filament\Pages\StepByStepTutorial;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\HumanResources;
use Modules\Tutorial\Services\TutorialProgress;
use Modules\Tutorial\Tests\Concerns\RunsTutorials;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class HumanResourcesScenarioTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, RunsTutorials;

    private Tenant $primary;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        app(DefaultRoles::class)->install();
        $this->roleWithPermissions(Utils::getPanelUserRoleName(), []);
        $this->owner = User::factory()->superAdmin()->create();
        $this->actingInPanel($this->owner, $this->primary);
    }

    public function test_auto_complete_runs_the_paperwork_and_starting_again_deletes_it(): void
    {
        Livewire::test(StepByStepTutorial::class)
            ->callAction(TestAction::make('autoComplete')->arguments(['scenario' => 'human-resources']))
            ->assertNotified('همه‌ی گام‌ها انجام شد');

        $run = TutorialRun::query()->where('user_id', $this->owner->id)->sole();
        $this->assertNotNull($run->completed_at);
        $this->assertCount(count((new HumanResources)->missions()), $run->completed_missions);

        $employee = $run->made(Employee::class)->whereNotNull('job_application_id')->sole();
        $this->assertSame('لیلا فرهادی‌نیا', $employee->user->name, 'The edit in the workflow reached the account.');
        $this->assertSame(
            ['started', 'edited', 'approved', 'returned', 'approved', 'approved', 'commented', 'completed'],
            WorkflowCase::withoutTenantScope()->sole()->actions->map(fn ($action): string => $action->type->value)->all(),
        );
        $this->assertSame(2, $run->made(Document::class)->count(), 'The ID card moved to the employee; the health certificate was added.');

        $contracts = $run->made(Contract::class)->orderBy('id')->get();
        $main = $contracts->first();
        $this->assertSame(SignedVia::Electronic, $main->signed_via);
        $this->assertSame(300000000, (int) collect($main->answers)->firstWhere('key', 'tutorial_monthly_salary')['value']);
        $this->assertSame($main->number.'/1', $contracts->firstWhere('kind', ContractKind::Amendment)->number);
        $this->assertSame(SignedVia::Paper, $contracts->firstWhere('kind', ContractKind::Amendment)->signed_via);
        $this->assertSame($main->ends_on->addDay()->toDateString(), $contracts->firstWhere('kind', ContractKind::Renewal)->starts_on->toDateString());
        $this->assertSame(ContractStatus::Cancelled, $contracts->last()->status);

        Livewire::test(StepByStepTutorial::class)->callAction('reset')->assertNotified('آموزش از اول');

        $this->assertModelMissing($employee);
        $this->assertModelMissing($main);
        $this->assertSame(0, Contract::withoutTenantScope()->count());
    }

    public function test_the_workflow_needs_every_step_setting(): void
    {
        Livewire::test(StepByStepTutorial::class)->call('start', 'human-resources');
        $run = TutorialRun::query()->where('user_id', $this->owner->id)->sole();
        $scenario = new HumanResources;
        $progress = app(TutorialProgress::class);

        foreach (['branch', 'document-types', 'contract-type', 'amendment-type'] as $key) {
            $this->assertSame($key, $progress->refresh($run->fresh())?->key);
            $scenario->mission($key)->performFor($run->fresh(), $this->owner);
        }

        $this->assertSame('workflow', $progress->refresh($run->fresh())?->key);
    }
}

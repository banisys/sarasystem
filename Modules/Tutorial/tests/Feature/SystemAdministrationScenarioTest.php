<?php

namespace Modules\Tutorial\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowCase;
use App\Workflow\CaseActionType;
use App\Workflow\CaseStatus;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Tutorial\Filament\Pages\StepByStepTutorial;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Scenario;
use Modules\Tutorial\Scenarios\Scenarios;
use Modules\Tutorial\Scenarios\SystemAdministration;
use Modules\Tutorial\Services\TutorialProgress;
use Modules\Tutorial\Tests\Concerns\RunsTutorials;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SystemAdministrationScenarioTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, RunsTutorials;

    private Tenant $primary;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        app(DefaultRoles::class)->install();
        $this->roleWithPermissions(Utils::getPanelUserRoleName(), []);
        $this->owner = User::factory()->superAdmin()->create();
    }

    public function test_auto_complete_does_every_mission_and_starting_again_deletes_it(): void
    {
        $this->actingInPanel($this->owner, $this->primary);

        Livewire::test(StepByStepTutorial::class)
            ->callAction(TestAction::make('autoComplete')->arguments(['scenario' => 'system-administration']))
            ->assertNotified('همه‌ی گام‌ها انجام شد');

        $run = TutorialRun::query()->where('user_id', $this->owner->id)->sole();
        $this->assertNotNull($run->completed_at);
        $this->assertCount(count((new SystemAdministration)->missions()), $run->completed_missions);

        [$east, $west] = $run->made(Tenant::class)->oldest('id')->get()->all();
        $this->assertFalse($west->is_active);
        $hamid = User::query()->where('national_code', '0030050073')->sole();
        $this->assertNotSame([], $hamid->roleIdsIn($east));
        $this->assertNotSame([], $hamid->roleIdsIn($west));

        $case = WorkflowCase::withoutTenantScope()->sole();
        $this->assertSame(CaseStatus::Rejected, $case->status);
        $this->assertSame(
            ['started', 'claimed', 'commented', 'approved', 'returned', 'approved', 'rejected'],
            $case->actions->map(fn ($action): string => $action->type->value)->all(),
        );

        Livewire::test(StepByStepTutorial::class)->callAction('reset')->assertNotified('آموزش از اول');

        $this->assertModelMissing($east);
        $this->assertModelMissing($hamid);
        $this->assertModelMissing($case);
        $this->assertFalse(Role::query()->where('name', 'librarian')->exists());
    }

    public function test_people_tick_only_with_the_roles_in_the_right_tenants(): void
    {
        $run = TutorialRun::query()->create(['user_id' => $this->owner->id, 'scenario' => 'system-administration', 'completed_missions' => []]);
        $progress = app(TutorialProgress::class);
        $current = fn (): ?string => $progress->refresh($run->fresh())?->key;

        $east = Tenant::factory()->create();
        $this->madeInRun($run, $east);
        $this->assertSame('tenants', $current(), 'Two branches are needed.');
        $west = Tenant::factory()->create();
        $this->madeInRun($run, $west);

        $manager = User::factory()->create();
        $this->madeInRun($run, $manager);
        $this->grantRole($manager, $east, DefaultRoles::TENANT_MANAGER);
        $this->assertSame('manager', $current(), 'The manager needs panel access.');
        $this->makeStaff($manager, $east);

        $role = $this->roleWithPermissions('librarian', ['ViewAny:User']);
        $this->madeInRun($run, $role);
        $this->assertSame('two-tenants', $current());

        $hamid = User::factory()->create();
        $this->madeInRun($run, $hamid);
        $this->grantRole($hamid, $east, DefaultRoles::STAFF);
        $this->assertSame('two-tenants', $current(), 'Hamid also needs the new role in the second branch.');
        $this->grantRole($hamid, $west, 'librarian');
        $this->assertSame('manager-adds-staff', $current());

        $samira = User::factory()->create();
        $this->madeInRun($run, $samira);
        $this->grantRole($samira, $west, DefaultRoles::STAFF);
        $this->assertSame('manager-adds-staff', $current(), 'She is added in the first branch.');
        $this->grantRole($samira, $east, DefaultRoles::STAFF);
        $this->assertSame('deactivate', $current());
    }

    public function test_the_case_missions_follow_its_history(): void
    {
        $this->actingInPanel($this->owner, $this->primary);
        Livewire::test(StepByStepTutorial::class)->call('start', 'system-administration');
        $run = TutorialRun::query()->where('user_id', $this->owner->id)->sole();
        $scenario = new SystemAdministration;
        $progress = app(TutorialProgress::class);

        // Up to the application, by auto-complete one mission at a time.
        foreach (['tenants', 'manager', 'custom-role', 'two-tenants', 'manager-adds-staff', 'deactivate', 'workflow', 'job-position', 'job-application'] as $key) {
            $this->assertSame($key, $progress->refresh($run->fresh())?->key);
            $scenario->mission($key)->performFor($run->fresh(), $this->owner);
        }

        $this->assertSame('claim-and-approve', $progress->refresh($run->fresh())?->key);
        $case = WorkflowCase::withoutTenantScope()->sole();
        $this->assertSame(CaseActionType::Started, $case->actions->sole()->type);
    }

    public function test_scenarios_whose_modules_are_off_are_not_offered(): void
    {
        $scenarios = app(Scenarios::class);
        $scenarios->register(NeedsAMissingModule::class);

        $this->assertArrayNotHasKey('needs-missing', $scenarios->all());
        $this->assertArrayHasKey('system-administration', $scenarios->all());

        $this->actingInPanel($this->owner, $this->primary);
        Livewire::test(StepByStepTutorial::class)->call('start', 'needs-missing')->assertStatus(404);
    }
}

class NeedsAMissingModule extends Scenario
{
    public function key(): string
    {
        return 'needs-missing';
    }

    public function title(): string
    {
        return 'Needs a missing module';
    }

    public function description(): string
    {
        return '';
    }

    public function chapters(): array
    {
        return [];
    }

    public function requiredModules(): array
    {
        return ['NoSuchModule'];
    }
}

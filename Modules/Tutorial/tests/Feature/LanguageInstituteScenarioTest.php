<?php

namespace Modules\Tutorial\Tests\Feature;

use App\Auth\Impersonation;
use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Support\Identifiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Term;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;
use Modules\Tutorial\Filament\Pages\StepByStepTutorial;
use Modules\Tutorial\Livewire\TutorialBar;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\LanguageInstitute;
use Modules\Tutorial\Scenarios\Mission;
use Modules\Tutorial\Services\TutorialMode;
use Modules\Tutorial\Services\TutorialProgress;
use Modules\Tutorial\Tests\Concerns\RunsTutorials;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class LanguageInstituteScenarioTest extends TestCase
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
        $this->owner = User::factory()->superAdmin()->create();
    }

    public function test_fill_values_are_valid_for_the_forms(): void
    {
        $run = TutorialRun::query()->create(['user_id' => $this->owner->getKey(), 'scenario' => 'language-institute']);
        $missions = collect((new LanguageInstitute)->chapters())->flatten()->keyBy(fn (Mission $mission): string => $mission->key);

        $this->assertSame('vanak', $missions['tenant']->fillFor($run)['slug']);

        $registrar = $missions['registrar']->fillFor($run);
        $this->assertTrue(Identifiers::isValidNationalCode($registrar['national_code']));
        $this->assertNotEmpty($registrar['role_ids']);

        $this->assertSame((new JobApplication)->getMorphClass(), $missions['hiring-workflow']->fillFor($run)['subject_type']);
        $this->assertNotEmpty($missions['hiring']->fillFor($run)['role_ids']);

        $this->assertSame(now()->toDateString(), $missions['term']->fillFor($run)['starts_on']);
    }

    public function test_missions_open_one_after_another_as_the_rows_appear(): void
    {
        $run = TutorialRun::query()->create(['user_id' => $this->owner->id, 'scenario' => 'language-institute', 'completed_missions' => []]);
        $progress = app(TutorialProgress::class);
        $current = fn (): ?string => $progress->refresh($run->fresh())?->key;

        $this->assertSame('tenant', $current());

        // Done out of order: the class term is not ticked before its turn.
        $this->madeInRun($run, Term::factory()->for($this->primary)->create());
        $this->assertSame('tenant', $current());

        $branch = Tenant::factory()->create();
        $this->madeInRun($run, $branch);
        $this->assertSame('registrar', $current());

        $registrar = $this->employee($run, $branch, DefaultRoles::REGISTRAR);
        $this->assertSame('registrar', $current(), 'The registrar needs panel access.');
        $this->makeStaff($registrar, $branch);
        $this->assertSame('hiring-workflow', $current());

        $hiringWorkflow = Workflow::factory()->create(['subject_type' => (new JobApplication)->getMorphClass(), 'steps' => [['name' => 'مصاحبه', 'tenant_id' => $this->primary->id, 'assignee_type' => 'user', 'user_id' => $this->owner->id, 'actions' => []]]]);
        $this->madeInRun($run, $hiringWorkflow);
        $this->assertSame('job-position', $current());

        $position = JobPosition::factory()->for($this->primary)->create(['workflow_id' => $hiringWorkflow->id, 'is_active' => true]);
        $this->madeInRun($run, $position);
        $this->assertSame('job-application', $current());

        $application = JobApplication::factory()->for($this->primary)->create(['job_position_id' => $position->id]);
        $this->madeInRun($run, $application);
        $this->assertSame('hiring', $current());

        $teacher = $this->employee($run, $branch, DefaultRoles::INSTRUCTOR);
        $this->assertSame('hiring', $current(), 'The instructor comes from the hired application.');
        $application->update(['status' => ApplicationStatus::Hired]);
        $this->assertSame('workflow', $current());

        $workflow = Workflow::factory()->create(['subject_type' => (new Preregistration)->getMorphClass(), 'steps' => [['name' => 'بررسی', 'tenant_id' => $branch->id, 'assignee_type' => 'role', 'role_id' => 1, 'actions' => []]]]);
        $this->madeInRun($run, $workflow);
        $this->assertSame('program', $current());

        $this->madeInRun($run, Program::factory()->for($this->primary)->create(['workflow_id' => $workflow->id]));
        $this->assertSame('preregistration', $current());

        $this->madeInRun($run, Preregistration::factory()->for($this->primary)->create());
        $this->assertSame('admission', $current());

        $learner = User::factory()->create();
        $this->madeInRun($run, Student::factory()->for($branch)->create(['user_id' => $learner->id]));
        // The term made earlier ticks as soon as its turn comes.
        $this->assertSame('classroom', $current());

        $classroom = Classroom::factory()->for($branch)->create();
        $this->madeInRun($run, $classroom);
        $this->assertSame('members', $current());

        $attendeeRole = ClassRole::factory()->for($this->primary)->attendee()->create();
        $teacherRole = ClassRole::factory()->for($this->primary)->teacher()->create();
        $this->madeInRun($run, ClassMember::factory()->create(['classroom_id' => $classroom->id, 'tenant_id' => $branch->id, 'user_id' => $learner->id, 'class_role_id' => $attendeeRole->id]));
        $this->assertSame('members', $current());
        $this->madeInRun($run, ClassMember::factory()->create(['classroom_id' => $classroom->id, 'tenant_id' => $branch->id, 'user_id' => $teacher->id, 'class_role_id' => $teacherRole->id]));
        $this->assertSame('schedule', $current());

        $session = ClassSession::factory()->create(['classroom_id' => $classroom->id]);
        $this->madeInRun($run, $session);
        $this->assertSame('attendance', $current());

        $this->madeInRun($run, Attendance::factory()->create(['session_id' => $session->id, 'classroom_id' => $classroom->id, 'tenant_id' => $branch->id, 'user_id' => $learner->id]));
        $this->assertNull($current());
        $this->assertNotNull($run->fresh()->completed_at);
    }

    public function test_the_owner_signs_in_as_the_account_a_mission_is_done_with(): void
    {
        $run = $this->startTutorial($this->owner);
        $branch = Tenant::factory()->create();
        $this->madeInRun($run, $branch);
        $registrar = $this->employee($run, $branch, DefaultRoles::REGISTRAR);
        $this->makeStaff($registrar, $branch);
        $run->update(['completed_missions' => ['tenant' => now(), 'registrar' => now(), 'hiring-workflow' => now(), 'job-position' => now(), 'job-application' => now(), 'hiring' => now(), 'workflow' => now(), 'program' => now(), 'preregistration' => now()]]);

        $this->actingAs($this->owner)->post(route('tutorial.act-as'))->assertRedirect();

        $this->assertAuthenticatedAs($registrar);
        $this->assertSame($this->owner->id, session(Impersonation::SESSION_KEY));
        // Still the owner's tutorial while signed in as someone else.
        $this->assertTrue(app(TutorialMode::class)->run()->is($run));
    }

    public function test_act_as_needs_a_mission_done_with_another_account(): void
    {
        $this->startTutorial($this->owner);

        $this->actingAs($this->owner)->post(route('tutorial.act-as'))->assertNotFound();
    }

    public function test_only_super_admins_see_the_tutorial_page(): void
    {
        $staff = User::factory()->create();
        $this->makeStaff($staff, $this->primary);

        $this->actingAs($staff)->get(StepByStepTutorial::getUrl(panel: 'admin', tenant: $this->primary))->assertForbidden();
        $this->actingAs($this->owner)->get(StepByStepTutorial::getUrl(panel: 'admin', tenant: $this->primary))->assertOk()->assertSee('آموزشگاه زبان');
    }

    public function test_starting_pausing_and_starting_again_from_the_page(): void
    {
        $this->actingInPanel($this->owner, $this->primary);

        Livewire::test(StepByStepTutorial::class)->call('start', 'language-institute');
        $run = TutorialRun::query()->where('user_id', $this->owner->id)->sole();
        $this->assertSame($run->id, session(TutorialMode::SESSION_KEY));

        Livewire::test(TutorialBar::class)->assertSee('ساخت '.config('sarasystem.tenant_label'));

        $branch = Tenant::factory()->create();
        $this->assertTrue($run->made(Tenant::class)->whereKey($branch->id)->exists());

        Livewire::test(StepByStepTutorial::class)->call('pause');
        $this->assertFalse(session()->has(TutorialMode::SESSION_KEY));
        Livewire::test(TutorialBar::class)->assertDontSee('ساخت '.config('sarasystem.tenant_label'));

        Livewire::test(StepByStepTutorial::class)->call('resume')->callAction('reset')->assertNotified('آموزش از اول');

        $this->assertModelMissing($branch);
        $this->assertModelMissing($run);
    }

    private function employee(TutorialRun $run, Tenant $branch, string $role): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $branch, $role);
        $this->madeInRun($run, Employee::factory()->for($branch)->create(['user_id' => $user->id]));

        return $user;
    }
}

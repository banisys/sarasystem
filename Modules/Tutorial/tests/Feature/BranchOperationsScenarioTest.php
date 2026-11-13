<?php

namespace Modules\Tutorial\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\DefaultClassRoles;
use Modules\Exam\Models\Exam;
use Modules\Student\Models\Program;
use Modules\Tutorial\Filament\Pages\StepByStepTutorial;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\BranchOperations;
use Modules\Tutorial\Services\TutorialProgress;
use Modules\Tutorial\Tests\Concerns\RunsTutorials;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BranchOperationsScenarioTest extends TestCase
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
        app(DefaultClassRoles::class)->install();
        $this->roleWithPermissions(Utils::getPanelUserRoleName(), []);
        $this->owner = User::factory()->superAdmin()->create();
        $this->actingInPanel($this->owner, $this->primary);
    }

    public function test_auto_complete_runs_the_daily_changes_and_starting_again_deletes_them(): void
    {
        Livewire::test(StepByStepTutorial::class)
            ->callAction(TestAction::make('autoComplete')->arguments(['scenario' => 'branch-operations']))
            ->assertNotified('همه‌ی گام‌ها انجام شد');

        $run = TutorialRun::query()->where('user_id', $this->owner->id)->sole();
        $this->assertNotNull($run->completed_at);
        $this->assertCount(count((new BranchOperations)->missions()), $run->completed_missions);

        [$pounak, $tajrish] = $run->made(Tenant::class)->oldest('id')->get()->all();
        $bahareh = User::query()->where('national_code', '0140160183')->sole();
        $this->assertNotSame([], $bahareh->roleIdsIn($pounak));
        $this->assertNotSame([], $bahareh->roleIdsIn($tajrish));

        $classroom = $run->made(Classroom::class)->where('tenant_id', $pounak->id)->whereNull('parent_id')->sole();
        $this->assertSame(1, $run->made(Classroom::class)->where('parent_id', $classroom->id)->count());
        $sessions = $run->made(ClassSession::class)->where('classroom_id', $classroom->id)->get();
        $this->assertFalse($sessions->contains(fn (ClassSession $session): bool => $session->starts_at->isSameDay(today()->addWeek())), 'The branch holiday has no session.');
        $this->assertTrue($sessions->contains(fn (ClassSession $session): bool => $session->hasSubstitute()));
        $this->assertSame(1, $sessions->where('status', SessionStatus::Cancelled)->count());
        $this->assertSame(1, $sessions->where('kind', SessionKind::MakeUp)->count());
        $examSession = $sessions->where('kind', SessionKind::Exam)->sole();
        $this->assertTrue($examSession->starts_at->isSameDay(today()->addDays(6)));
        $this->assertNull($examSession->schedule_id);
        $this->assertArrayHasKey('exam-schedule', $run->completed_missions);
        $this->assertSame(1, $sessions->whereNotNull('original_starts_at')->count());
        $this->assertSame([AttendanceStatus::Present, AttendanceStatus::Late], $run->made(Attendance::class)->orderBy('id')->pluck('status')->all());
        $this->assertSame(1, $run->made(ClassMember::class)->whereNotNull('left_on')->count());
        $this->assertSame(1, $run->made(Program::class)->where('is_active', false)->count());

        if (Module::isEnabled('Exam')) {
            $exam = $run->made(Exam::class)->where('context_type', 'class-session')->sole();
            $this->assertTrue($sessions->contains('id', $exam->context_id));
            $this->assertCount(1, $exam->question_snapshot);
            $this->assertTrue($exam->is_published);
            $this->assertArrayHasKey('session-exam', $run->completed_missions);
        }

        Livewire::test(StepByStepTutorial::class)->callAction('reset')->assertNotified('آموزش از اول');

        $this->assertModelMissing($pounak);
        $this->assertModelMissing($bahareh);
        $this->assertSame(0, ClassSession::withoutTenantScope()->count());
        if (Module::isEnabled('Exam')) {
            $this->assertSame(0, Exam::withoutTenantScope()->count());
        }
    }

    public function test_the_program_missions_follow_where_programs_are_offered(): void
    {
        Livewire::test(StepByStepTutorial::class)->call('start', 'branch-operations');
        $run = TutorialRun::query()->where('user_id', $this->owner->id)->sole();
        $scenario = new BranchOperations;
        $progress = app(TutorialProgress::class);

        foreach (['branches', 'officers', 'teachers', 'workflow'] as $key) {
            $scenario->mission($key)->performFor($run->fresh(), $this->owner);
        }
        $this->assertSame('programs', $progress->refresh($run->fresh())?->key);

        // Offered everywhere is not "in some branches".
        $everywhere = Program::factory()->for($this->primary)->create(['all_tenants' => true, 'workflow_id' => $run->made(Workflow::class)->value('id')]);
        $this->madeInRun($run, $everywhere);
        $this->assertSame('programs', $progress->refresh($run->fresh())?->key);

        $scenario->mission('programs')->performFor($run->fresh(), $this->owner);
        $this->assertSame('program-field', $progress->refresh($run->fresh())?->key);
    }
}

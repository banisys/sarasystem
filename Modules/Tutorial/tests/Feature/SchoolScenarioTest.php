<?php

namespace Modules\Tutorial\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Services\DefaultClassRoles;
use Modules\Exam\Enums\AttemptStatus;
use Modules\Exam\Models\ExamAttempt;
use Modules\School\Models\Enrollment;
use Modules\School\Models\Grade;
use Modules\School\Models\Score;
use Modules\School\Services\ReportCards;
use Modules\Tutorial\Filament\Pages\StepByStepTutorial;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\School;
use Modules\Tutorial\Services\TutorialProgress;
use Modules\Tutorial\Tests\Concerns\RunsTutorials;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SchoolScenarioTest extends TestCase
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
        app(DefaultClassRoles::class)->install();
        $this->roleWithPermissions(Utils::getPanelUserRoleName(), []);
        $this->owner = User::factory()->superAdmin()->create();
        $this->actingInPanel($this->owner, $this->primary);
    }

    public function test_auto_complete_runs_the_school_year_and_starting_again_deletes_it(): void
    {
        Livewire::test(StepByStepTutorial::class)
            ->callAction(TestAction::make('autoComplete')->arguments(['scenario' => 'school']))
            ->assertNotified('همه‌ی گام‌ها انجام شد');

        $run = TutorialRun::query()->where('user_id', $this->owner->id)->sole();
        $this->assertNotNull($run->completed_at);
        $this->assertCount(count((new School)->missions()), $run->completed_missions);

        $amir = User::query()->where('national_code', '0080100120')->sole();
        $father = User::query()->where('national_code', '0090110137')->sole();
        $this->assertSame($father->mobile, $amir->mobile, 'Amir signs in with his father\'s mobile.');
        $this->assertTrue(Guardianship::query()->where('ward_id', $amir->id)->where('guardian_id', $father->id)->exists());

        $this->assertSame(AttendanceStatus::Absent, $run->made(Attendance::class)->sole()->status);
        $this->assertSame(18.0, (float) $run->made(HomeworkSubmission::class)->sole()->score);
        $this->assertSame(18.0, (float) $run->made(Score::class)->sole()->score);
        // The holiday next week has no session.
        $this->assertFalse($run->made(ClassSession::class)->whereDate('starts_at', today()->addWeek())->exists());

        $school = $run->made(Tenant::class)->sole();
        $cards = app(ReportCards::class)->publishedFor($amir->id, $school->id);
        $this->assertCount(1, $cards);
        $this->assertSame(['هفتم', 'هشتم'], Enrollment::withoutTenantScope()->where('user_id', $amir->id)->orderBy('term_id')->get()
            ->map(fn (Enrollment $enrollment): string => Grade::withoutTenantScope()->find($enrollment->grade_id)->title)->all());
        $this->assertSame(AttemptStatus::Completed, $run->made(ExamAttempt::class)->sole()->status);

        Livewire::test(StepByStepTutorial::class)->callAction('reset')->assertNotified('آموزش از اول');

        $this->assertModelMissing($school);
        $this->assertModelMissing($amir);
        $this->assertModelMissing($father);
        $this->assertSame(0, Enrollment::withoutTenantScope()->count());
    }

    public function test_the_school_structure_missions_wait_for_their_rows(): void
    {
        Livewire::test(StepByStepTutorial::class)->call('start', 'school');
        $run = TutorialRun::query()->where('user_id', $this->owner->id)->sole();
        $scenario = new School;
        $progress = app(TutorialProgress::class);

        foreach (['school', 'officer', 'teacher', 'grades', 'curriculum', 'year', 'workflow', 'program'] as $key) {
            $this->assertSame($key, $progress->refresh($run->fresh())?->key);
            $scenario->mission($key)->performFor($run->fresh(), $this->owner);
        }

        $this->assertSame('preregistration', $progress->refresh($run->fresh())?->key);
    }
}

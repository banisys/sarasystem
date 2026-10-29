<?php

namespace Modules\School\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Filament\Resources\Classrooms\Pages\ViewClassroom;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;
use Modules\Class\Models\Subject;
use Modules\Class\Models\Term;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\SessionPlanner;
use Modules\School\Enums\DescriptiveLevel;
use Modules\School\Enums\ScoreScale;
use Modules\School\Filament\Resources\ScorePeriods\ScorePeriodResource;
use Modules\School\Models\Curriculum;
use Modules\School\Models\Enrollment;
use Modules\School\Models\Grade;
use Modules\School\Models\Score;
use Modules\School\Models\ScorePeriod;
use Modules\School\Models\Stage;
use Modules\School\Services\Enrollments;
use Modules\School\Services\ReportCards;
use Modules\School\Services\SchoolException;
use Modules\School\Services\ScoreBook;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Score periods, the teachers' score book and report cards (ADR 0032).
 * Grade 7 has math (coefficient 2) and Persian (coefficient 1); «هفتم الف»
 * has a math teacher. Today is 2026-10-29; the period «آبان» covers
 * 2026-10-23 to 2026-11-21 and closes for teachers on 2026-11-28.
 */
class ScoresAndReportCardsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private Classroom $classroom;

    private ScorePeriod $aban;

    private Subject $math;

    private Subject $persian;

    private User $teacher;

    private User $amir;

    private User $sara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));
        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'شعبه غرب']);
        $this->roleWithPermissions('member', []);

        $year = Term::factory()->for($this->primary)->create(['title' => '۱۴۰۵-۱۴۰۶', 'starts_on' => '2026-09-23', 'ends_on' => '2027-06-21']);
        $seventh = $this->primary->execute(function () use ($year): Grade {
            $stage = Stage::query()->create(['title' => 'متوسطه اول', 'scale' => ScoreScale::Numeric]);
            $grade = Grade::query()->create(['stage_id' => $stage->id, 'title' => 'هفتم']);
            $this->math = Subject::query()->create(['title' => 'ریاضی']);
            $this->persian = Subject::query()->create(['title' => 'ادبیات فارسی']);
            Curriculum::query()->create(['grade_id' => $grade->id, 'subject_id' => $this->math->id, 'coefficient' => 2, 'sort' => 1]);
            Curriculum::query()->create(['grade_id' => $grade->id, 'subject_id' => $this->persian->id, 'coefficient' => 1, 'sort' => 2]);
            $this->aban = ScorePeriod::query()->create(['term_id' => $year->id, 'title' => 'آبان', 'starts_on' => '2026-10-23', 'ends_on' => '2026-11-21', 'entry_closes_on' => '2026-11-28']);

            return $grade;
        });

        $this->west->makeCurrent();
        $this->classroom = Classroom::factory()->for($this->west)->create(['title' => 'هفتم الف', 'term_id' => $year->id]);
        app(Enrollments::class)->setClassroomGrade($this->classroom, $seventh->id, null);
        [$this->teacher, $this->amir, $this->sara] = [$this->person('معلم ریاضی'), $this->person('امیر محمدی'), $this->person('سارا جعفری')];
        $members = app(ClassMembers::class);
        $members->add($this->classroom, ClassRole::factory()->for($this->primary)->attendee()->create(), [$this->amir->id, $this->sara->id], CarbonImmutable::parse('2026-09-23'));
        $members->add($this->classroom, ClassRole::factory()->for($this->primary)->teacher()->create(), [$this->teacher->id]);
        app(SessionPlanner::class)->saveSchedule(null, $this->classroom, [
            'frequency' => Frequency::Weekly, 'weekdays' => [Weekday::Saturday->value], 'starts_on' => '2026-10-31', 'ends_on' => '2026-11-14',
            'start_time' => '08:00', 'end_time' => '09:30', 'subject_id' => $this->math->id, 'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_periods_are_defined_in_the_primary_tenant_only(): void
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);
        $this->actingAs($admin);

        $this->get(ScorePeriodResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(ScorePeriodResource::getUrl('index', tenant: $this->west))->assertForbidden();
    }

    public function test_the_subjects_teacher_enters_scores_until_the_period_closes(): void
    {
        $this->as($this->teacher, route('school.scores.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('School::ScoreBooks')
            ->has('assignments', 1)
            ->where('assignments.0.subject', 'ریاضی')
            ->where('assignments.0.periods.0.is_open', true));

        $url = route('school.scores.store', ['classroom' => $this->classroom, 'subjectId' => $this->math->id]);
        $this->as($this->teacher, $url, 'post', ['period_id' => $this->aban->id, 'rows' => [['user_id' => $this->amir->id, 'score' => 21]]])
            ->assertSessionHasErrors('rows.0.score');
        $this->as($this->teacher, $url, 'post', ['period_id' => $this->aban->id, 'rows' => [
            ['user_id' => $this->amir->id, 'score' => '18.5', 'note' => 'خوب'], ['user_id' => $this->sara->id, 'score' => 20],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('18.50', Score::query()->where('user_id', $this->amir->id)->sole()->score);

        // Not Persian (another teacher's), not a stranger, not after closing.
        $this->as($this->teacher, route('school.scores.store', ['classroom' => $this->classroom, 'subjectId' => $this->persian->id]), 'post', [
            'period_id' => $this->aban->id, 'rows' => [['user_id' => $this->amir->id, 'score' => 15]],
        ])->assertSessionHasErrors(['form' => 'شما مدرس این درس در این کلاس نیستید.']);
        $this->as($this->sara, route('school.scores.show', ['classroom' => $this->classroom, 'subjectId' => $this->math->id]))->assertNotFound();

        $this->travelTo(CarbonImmutable::parse('2026-11-29 08:00'));
        $this->as($this->teacher, $url, 'post', ['period_id' => $this->aban->id, 'rows' => [['user_id' => $this->amir->id, 'score' => 19]]])
            ->assertSessionHasErrors(['form' => 'ثبت نمره‌ی این دوره بسته است.']);

        // An empty row removes the score.
        $this->travelTo(CarbonImmutable::parse('2026-11-01 08:00'));
        $this->as($this->teacher, $url, 'post', ['period_id' => $this->aban->id, 'rows' => [['user_id' => $this->sara->id, 'score' => '']]])->assertSessionHasNoErrors();
        $this->assertFalse(Score::query()->where('user_id', $this->sara->id)->exists());
    }

    public function test_a_primary_school_class_gets_descriptive_levels_from_the_admin_panel(): void
    {
        Stage::withoutTenantScope()->update(['scale' => ScoreScale::Descriptive]);
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);

        $this->actingInPanel($admin, $this->west);
        $page = Livewire::test(ViewClassroom::class, ['record' => $this->classroom->getRouteKey()])
            ->mountAction('scoreBook')
            ->setActionData(['period_id' => $this->aban->id, 'subject_id' => $this->persian->id]);

        $rows = $page->instance()->mountedActions[0]['data']['rows'];
        $this->assertSame(['امیر محمدی', 'سارا جعفری'], array_column($rows, 'name'));
        $page->setActionData(['rows' => array_map(fn (array $row): array => [...$row, 'level' => DescriptiveLevel::VeryGood->value], $rows)])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame([DescriptiveLevel::VeryGood, DescriptiveLevel::VeryGood], Score::query()->orderBy('id')->get()->pluck('level')->all());
        $this->assertNull(Score::query()->first()->score);
    }

    public function test_a_report_card_has_the_weighted_average_absences_and_homework(): void
    {
        $book = app(ScoreBook::class);
        $book->record($this->classroom, $this->aban, $this->math->id, [$this->amir->id => ['score' => 18]], $this->teacher);
        $this->assertRefused(fn () => $book->record($this->classroom, $this->aban, $this->persian->id, [$this->amir->id => ['score' => 15]], $this->teacher), 'مدرس');
        Score::query()->create(['tenant_id' => $this->west->id, 'period_id' => $this->aban->id, 'user_id' => $this->amir->id, 'subject_id' => $this->persian->id, 'score' => 15, 'entered_at' => now()]);

        $first = ClassSession::query()->whereDate('starts_at', '2026-10-31')->sole();
        $second = ClassSession::query()->whereDate('starts_at', '2026-11-07')->sole();
        Attendance::factory()->create(['session_id' => $first->id, 'user_id' => $this->amir->id, 'status' => AttendanceStatus::Absent]);
        Attendance::factory()->create(['session_id' => $second->id, 'user_id' => $this->amir->id, 'status' => AttendanceStatus::Late]);
        $first->update(['homework' => 'x', 'homework_max_score' => 10]);
        HomeworkSubmission::factory()->create(['session_id' => $first->id, 'user_id' => $this->amir->id, 'score' => 8, 'graded_at' => now()]);

        $card = app(ReportCards::class)->build(Enrollment::query()->where('user_id', $this->amir->id)->sole(), $this->aban);

        // (18 × 2 + 15 × 1) / 3 = 17
        $this->assertSame('17', $card['average']);
        $this->assertSame([['ریاضی', '2', '18'], ['ادبیات فارسی', '1', '15']], array_map(fn (array $row): array => [$row['subject'], $row['coefficient'], $row['result']], $card['rows']));
        $this->assertSame([1, 0, 1], [$card['absences'], $card['excused'], $card['lates']]);
        $this->assertSame('16', $card['homework_average']);
    }

    public function test_learners_and_guardians_see_published_report_cards_only(): void
    {
        Score::query()->create(['tenant_id' => $this->west->id, 'period_id' => $this->aban->id, 'user_id' => $this->amir->id, 'subject_id' => $this->math->id, 'score' => 19, 'entered_at' => now()]);
        $father = $this->person('محمد محمدی');
        Guardianship::query()->create(['guardian_id' => $father->id, 'ward_id' => $this->amir->id, 'relationship' => 'father']);
        $enrollment = Enrollment::query()->where('user_id', $this->amir->id)->sole();

        $this->as($this->amir, route('school.report-cards.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('cards', []));
        $this->as($this->amir, route('school.report-cards.pdf', ['enrollment' => $enrollment, 'periodId' => $this->aban->id]))->assertNotFound();

        $this->aban->update(['is_published' => true]);
        $nav = collect($this->as($father, route('dashboard'))->viewData('page')['props']['navigation']);
        $this->assertTrue($nav->contains('title', 'کارنامه‌ی امیر محمدی'));

        $this->as($this->amir, route('school.report-cards.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('School::ReportCards')
            ->has('cards', 1)
            ->where('cards.0.rows.0.result', '19')
            ->where('cards.0.average', '19'));
        $this->as($father, route('school.report-cards.index', ['for' => $this->amir->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ward.name', 'امیر محمدی')
            ->has('cards', 1));

        $this->as($father, route('school.report-cards.pdf', ['enrollment' => $enrollment, 'periodId' => $this->aban->id, 'for' => $this->amir->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->as($this->sara, route('school.report-cards.pdf', ['enrollment' => $enrollment, 'periodId' => $this->aban->id]))->assertNotFound();

        // Published: teachers can no longer change it.
        $this->assertRefused(fn () => app(ScoreBook::class)->record($this->classroom, $this->aban, $this->math->id, [$this->amir->id => ['score' => 10]], $this->teacher), 'بسته');
    }

    private function person(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->grantRole($user, $this->west, 'member');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function as(User $user, string $url, string $method = 'get', array $data = []): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([ResolveCurrentTenant::SESSION_KEY => $this->west->id])
            ->{$method}($url, $data);
    }

    private function assertRefused(callable $operation, string $message): void
    {
        try {
            $operation();
        } catch (SchoolException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }

        $this->fail("No error «{$message}».");
    }
}

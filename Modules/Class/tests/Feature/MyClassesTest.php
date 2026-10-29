<?php

namespace Modules\Class\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Guardianship;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\TeachingPlan;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\SessionPlanner;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * «کلاس‌های من» in the user panel (ADR 0026). Today is Thursday
 * 2026-10-29 (1405/08/07); the class meets on Saturdays and Mondays at 8:00.
 */
class MyClassesTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private Classroom $classroom;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));
        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
        $this->roleWithPermissions('member', []);

        $this->classroom = Classroom::factory()->for($this->west)->create(['title' => 'هفتم الف', 'mode' => 'online', 'meeting_url' => 'https://meet.example.com/7a']);
        $members = app(ClassMembers::class);
        $members->add($this->classroom, ClassRole::factory()->for($this->primary)->teacher()->create(), [($this->teacher = $this->person())->id]);
        $members->add($this->classroom, ClassRole::factory()->for($this->primary)->attendee()->create(), [($this->student = $this->person())->id], CarbonImmutable::parse('2026-10-01'));

        app(SessionPlanner::class)->saveSchedule(null, $this->classroom, [
            'frequency' => Frequency::Weekly, 'interval' => 1, 'weekdays' => [Weekday::Saturday->value, Weekday::Monday->value],
            'starts_on' => '2026-10-31', 'ends_on' => '2026-11-30', 'start_time' => '08:00', 'end_time' => '09:30', 'teacher_id' => $this->teacher->id,
            'teaching_plan_id' => TeachingPlan::factory()->for($this->primary)->withTopics([1, 1])->create()->id,
        ]);
    }

    public function test_the_week_shows_the_persons_sessions_and_the_holidays(): void
    {
        $other = Classroom::factory()->for($this->west)->create();
        ClassSession::factory()->create(['classroom_id' => $other->id, 'starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-01 11:00']);
        Holiday::factory()->for($this->west)->create(['title' => 'اردو', 'starts_on' => '2026-11-04', 'ends_on' => '2026-11-04']);

        $this->as($this->student, route('my-classes.index', ['date' => '2026-11-01']))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Class::Index')
            ->where('calendar.view', 'week')
            ->has('calendar.days', 7)
            ->where('calendar.days.0.date', '2026-10-31')
            ->where('calendar.days.0.sessions.0.class', 'هفتم الف')
            ->where('calendar.days.0.sessions.0.time', '08:00–09:30')
            ->where('calendar.days.0.sessions.0.meeting_url', 'https://meet.example.com/7a')
            ->where('calendar.days.1.sessions', [])
            ->where('calendar.days.2.sessions.0.class', 'هفتم الف')
            ->where('calendar.days.4.holiday', 'اردو')
            ->where('calendar.previous', '2026-10-24')
            ->has('classes', 1));
    }

    public function test_the_month_is_the_jalali_month_from_saturday(): void
    {
        // Aban 1405: 2026-10-23 (a Friday) to 2026-11-21.
        $this->as($this->student, route('my-classes.index', ['view' => 'month', 'date' => '2026-11-10']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.days.0.date', '2026-10-23')
            ->has('calendar.days', 30)
            ->where('calendar.leading_blanks', 6)
            ->where('calendar.next', '2026-11-22'));
    }

    public function test_a_learner_sees_the_session_and_their_attendance_but_no_forms(): void
    {
        $session = $this->sessionOn('2026-10-31');
        Attendance::factory()->create(['session_id' => $session->id, 'user_id' => $this->student->id, 'status' => AttendanceStatus::Late, 'late_minutes' => 5]);
        $session->update(['homework' => 'تمرین ۱', 'homework_due_on' => '2026-11-02']);

        $this->as($this->student, route('my-classes.session', $session))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Class::Session')
            ->where('role.is_attendee', true)
            ->where('myAttendance.status', 'تأخیر')
            ->where('myAttendance.late_minutes', 5)
            ->where('session.homework', 'تمرین ۱')
            ->where('session.planned_topics', ['سرفصل 1'])
            ->where('attendance', null)
            ->where('contentForm', null));

        $this->travelTo(CarbonImmutable::parse('2026-10-31 08:05'));
        $this->as($this->student, route('my-classes.attendance', $session), 'post', ['rows' => [['user_id' => $this->student->id, 'status' => 'present']]])
            ->assertSessionHasErrors('form');
    }

    public function test_the_teacher_takes_attendance_and_records_content_with_files(): void
    {
        Storage::fake('local');
        $session = $this->sessionOn('2026-10-31');

        $this->as($this->teacher, route('my-classes.attendance', $session), 'post', ['rows' => [['user_id' => $this->student->id, 'status' => 'present']]])
            ->assertSessionHasErrors(['form' => 'از کمی پیش از شروع جلسه باز می‌شود.']);

        $this->travelTo(CarbonImmutable::parse('2026-10-31 08:05'));
        $this->as($this->teacher, route('my-classes.session', $session))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('role.is_teacher', true)
            ->where('attendance.0.user_id', $this->student->id)
            ->where('attendance.0.status', 'present')
            ->has('contentForm.topics', 2));

        $this->as($this->teacher, route('my-classes.attendance', $session), 'post', ['rows' => [['user_id' => $this->student->id, 'status' => 'absent', 'note' => 'بیمار']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(AttendanceStatus::Absent, Attendance::query()->where('user_id', $this->student->id)->sole()->status);

        $topic = TeachingPlan::withoutTenantScope()->sole()->topics->first();
        $this->as($this->teacher, route('my-classes.content', $session), 'post', [
            'covered_topic_ids' => [$topic->id], 'summary' => 'مقدمه', 'homework' => 'تمرین ۲', 'homework_due_on' => '1405/08/12',
            'files' => [UploadedFile::fake()->create('worksheet.pdf', 50, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame([$topic->id], $session->covered_topic_ids);
        $this->assertSame('2026-11-03', $session->homework_due_on->toDateString());
        $this->assertSame(['worksheet.pdf'], array_column($session->materialLinks(), 'name'));
    }

    public function test_others_sessions_and_other_tenants_sessions_are_not_shown(): void
    {
        $stranger = $this->person();
        $session = $this->sessionOn('2026-10-31');
        $elsewhere = $this->primary->execute(fn () => ClassSession::factory()->create(['classroom_id' => Classroom::factory()->for($this->primary)->create()->id]));

        $this->as($stranger, route('my-classes.session', $session))->assertNotFound();
        $this->as($this->teacher, route('my-classes.session', $elsewhere))->assertNotFound();
    }

    public function test_the_header_link_is_shown_to_class_members_only(): void
    {
        $hasLink = fn (User $user): bool => collect($this->as($user, route('dashboard'))->viewData('page')['props']['navigation'])->contains('title', 'کلاس‌های من');

        $this->assertTrue($hasLink($this->student));
        $this->assertFalse($hasLink($this->person()));
    }

    public function test_a_guardian_sees_the_wards_classes_read_only(): void
    {
        $father = $this->person();
        $student = $this->student;
        Guardianship::query()->create(['guardian_id' => $father->id, 'ward_id' => $student->id, 'relationship' => 'father']);
        $session = $this->sessionOn('2026-10-31');
        Attendance::factory()->create(['session_id' => $session->id, 'user_id' => $student->id, 'status' => AttendanceStatus::Absent]);

        $nav = collect($this->as($father, route('dashboard'))->viewData('page')['props']['navigation']);
        $this->assertFalse($nav->contains('title', 'کلاس‌های من'));
        $this->assertTrue($nav->contains('url', route('my-classes.index', ['for' => $student->id])));

        $this->as($father, route('my-classes.index', ['for' => $student->id, 'date' => '2026-10-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ward.id', $student->id)
            ->where('calendar.days.0.sessions.0.my_attendance', 'غایب'));

        // Even while the session is open, a guardian gets no forms.
        $this->travelTo(CarbonImmutable::parse('2026-10-31 08:05'));
        $this->teacher->wards()->attach($student->id, ['relationship' => 'other']);
        $this->as($this->teacher, route('my-classes.session', ['session' => $session, 'for' => $student->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ward.name', $student->name)
            ->where('myAttendance.status', 'غایب')
            ->where('attendance', null)
            ->where('contentForm', null));
    }

    public function test_only_a_guardian_of_the_ward_may_look(): void
    {
        $this->as($this->person(), route('my-classes.index', ['for' => $this->student->id]))->assertNotFound();
        $this->as($this->person(), route('my-classes.session', ['session' => $this->sessionOn('2026-10-31'), 'for' => $this->student->id]))->assertNotFound();
    }

    private function sessionOn(string $day): ClassSession
    {
        return ClassSession::query()->whereDate('starts_at', $day)->sole();
    }

    private function person(): User
    {
        $user = User::factory()->create();
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
}

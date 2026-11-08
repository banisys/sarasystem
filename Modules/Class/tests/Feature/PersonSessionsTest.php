<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Filament\Resources\ClassSessions\Pages\LearnerSessions;
use Modules\Class\Filament\Resources\ClassSessions\Pages\TeacherSessions;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionCalendar;
use Modules\Employee\Filament\Resources\Employees\Pages\ListEmployees;
use Modules\Employee\Models\Employee;
use Modules\Student\Filament\Resources\Students\Pages\ListStudents;
use Modules\Student\Models\Student;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * A teacher's or a learner's sessions on a calendar, from the employees or
 * learners list (ADR 0048).
 * Now is Thursday 2026-10-29 (1405/08/07) 12:00.
 */
class PersonSessionsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private User $teacher;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));

        Tenant::factory()->create()->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
        $this->teacher = User::factory()->create(['name' => 'مریم احمدی']);
        $this->makeStaff($this->teacher, $this->west);
        $this->classroom = Classroom::factory()->for($this->west)->create(['title' => 'زبان مقدماتی الف']);
    }

    public function test_the_calendar_has_the_sessions_the_person_actually_teaches_in_the_tenant(): void
    {
        $someone = User::factory()->create();
        $this->classSession('2026-10-25 10:00', status: SessionStatus::Held);
        $this->classSession('2026-10-27 10:00', substitute: $someone);
        $this->classSession('2026-10-28 10:00', teacher: $someone, substitute: $this->teacher);
        $this->classSession('2026-10-30 10:00', status: SessionStatus::Cancelled);
        $this->classSession('2026-10-30 14:00', teacher: $someone);
        Tenant::factory()->create()->execute(fn () => ClassSession::factory()->taughtBy($this->teacher)->create([
            'classroom_id' => Classroom::factory()->for(Tenant::current())->create()->id,
            'starts_at' => '2026-10-26 10:00',
            'ends_at' => '2026-10-26 11:30',
        ]));

        $week = app(SessionCalendar::class)->forTeacher($this->teacher, 'week', CarbonImmutable::parse('2026-10-29'));

        $this->assertSame(['2026-10-25', '2026-10-28', '2026-10-30'], collect($week['days'])->filter(fn (array $day): bool => $day['sessions'] !== [])->pluck('date')->values()->all());
        $this->assertTrue($week['shows_classroom']);
        $this->assertNull($week['mode']);
        $this->assertSame('زبان مقدماتی الف', $week['days'][1]['sessions'][0]['classroom']);
        $this->assertTrue($week['days'][4]['sessions'][0]['is_substitute']);
        $this->assertSame(['held' => '1:30', 'planned' => '3:00'], $week['durations']);
    }

    public function test_the_page_shows_the_teachers_sessions_and_opens_only_theirs(): void
    {
        $mine = $this->classSession('2026-10-30 10:00');
        $notMine = $this->classSession('2026-10-30 14:00', teacher: User::factory()->create());

        $this->actingInPanel($this->admin(), $this->west);
        $this->get(ClassSessionResource::getUrl('teacher', ['user' => $this->teacher], tenant: $this->west))
            ->assertOk()
            ->assertSee('جلسه‌های تدریس مریم احمدی')
            ->assertSee('زبان مقدماتی الف');

        Livewire::test(TeacherSessions::class, ['user' => $this->teacher->id])
            ->assertSee('آبان 1405')
            ->assertSee('ساعت برگزارشده')
            ->assertDontSee('جلسه‌ی جبرانی / فوق‌العاده')
            ->assertSeeHtml(ClassSessionResource::getUrl('view', ['record' => $mine]))
            ->assertDontSeeHtml(ClassSessionResource::getUrl('view', ['record' => $notMine]));
    }

    public function test_the_page_needs_the_sessions_permission_and_a_person_of_the_tenant(): void
    {
        $stranger = User::factory()->create();
        $officer = User::factory()->create();
        $this->makeStaff($officer, $this->west);

        $this->actingInPanel($this->admin(), $this->west);
        $this->get(ClassSessionResource::getUrl('teacher', ['user' => $stranger], tenant: $this->west))->assertNotFound();

        $this->actingInPanel($officer, $this->west);
        $this->get(ClassSessionResource::getUrl('teacher', ['user' => $this->teacher], tenant: $this->west))->assertForbidden();
    }

    public function test_the_employees_list_links_to_the_calendar(): void
    {
        if (! class_exists(Employee::class)) {
            $this->markTestSkipped('The Employee module is off.');
        }

        $employee = Employee::factory()->for($this->west)->for($this->teacher)->create();

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ListEmployees::class)
            ->assertActionHasUrl(TestAction::make('teacherSessions')->table($employee), ClassSessionResource::getUrl('teacher', ['user' => $this->teacher], tenant: $this->west));
    }

    public function test_a_learners_calendar_has_their_classes_sessions_while_they_were_in_them(): void
    {
        $learner = User::factory()->create(['name' => 'سارا رضایی']);
        ClassMember::factory()->create(['classroom_id' => $this->classroom->id, 'user_id' => $learner->id, 'class_role_id' => ClassRole::factory()->attendee()->create()->id, 'joined_on' => '2026-10-26', 'left_on' => '2026-10-29']);
        $other = Classroom::factory()->for($this->west)->create(['title' => 'کارگاه']);
        ClassMember::factory()->create(['classroom_id' => $other->id, 'user_id' => $learner->id, 'class_role_id' => ClassRole::factory()->create()->id]);
        $this->classSession('2026-10-25 10:00');
        $absent = $this->classSession('2026-10-27 10:00', status: SessionStatus::Held);
        Attendance::factory()->create(['session_id' => $absent->id, 'user_id' => $learner->id, 'status' => AttendanceStatus::Absent]);
        $this->classSession('2026-10-30 10:00');
        ClassSession::factory()->create(['classroom_id' => $other->id, 'starts_at' => '2026-10-28 10:00', 'ends_at' => '2026-10-28 11:00']);
        $recorded = ClassSession::factory()->create(['classroom_id' => $other->id, 'starts_at' => '2026-10-29 08:00', 'ends_at' => '2026-10-29 09:00', 'status' => SessionStatus::Held]);
        Attendance::factory()->create(['session_id' => $recorded->id, 'user_id' => $learner->id, 'status' => AttendanceStatus::Present]);

        $week = app(SessionCalendar::class)->forLearner($learner, 'week', CarbonImmutable::parse('2026-10-29'));

        $this->assertSame(['2026-10-27', '2026-10-29'], collect($week['days'])->filter(fn (array $day): bool => $day['sessions'] !== [])->pluck('date')->values()->all());
        $this->assertSame(AttendanceStatus::Absent, $week['days'][3]['sessions'][0]['attendance']);
        $this->assertSame('مریم احمدی', $week['days'][3]['sessions'][0]['teacher']);
        $this->assertSame(1, $week['attendance']['غایب']);
        $this->assertSame(1, $week['attendance']['حاضر']);

        $this->makeStaff($learner, $this->west);
        $this->actingInPanel($this->admin(), $this->west);
        $this->get(ClassSessionResource::getUrl('learner', ['user' => $learner], tenant: $this->west))
            ->assertOk()
            ->assertSee('جلسه‌های کلاس سارا رضایی');
        Livewire::test(LearnerSessions::class, ['user' => $learner->id])
            ->assertDontSeeHtml(ClassSessionResource::getUrl('view', ['record' => $this->classSession('2026-10-25 12:00')]))
            ->assertSeeHtml(ClassSessionResource::getUrl('view', ['record' => $absent]));
    }

    public function test_the_learners_list_links_to_the_calendar(): void
    {
        if (! class_exists(Student::class)) {
            $this->markTestSkipped('The Student module is off.');
        }

        $student = Student::factory()->for($this->west)->for($this->teacher)->create();

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ListStudents::class)
            ->assertActionHasUrl(TestAction::make('learnerSessions')->table($student), ClassSessionResource::getUrl('learner', ['user' => $this->teacher], tenant: $this->west));
    }

    private function classSession(string $startsAt, SessionStatus $status = SessionStatus::Scheduled, ?User $teacher = null, ?User $substitute = null): ClassSession
    {
        $start = CarbonImmutable::parse($startsAt);

        return ClassSession::factory()->taughtBy($teacher ?? $this->teacher, $substitute)->create([
            'classroom_id' => $this->classroom->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(90),
            'status' => $status,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin(Tenant::primary());

        return $admin;
    }
}

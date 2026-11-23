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
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Enums\Weekday;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\Subject;
use Modules\Class\Models\TeachingPlan;
use Modules\Class\Models\Term;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\SessionPlanner;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * «تقویم آموزشی» in the user panel (ADR 0026). Today is Thursday
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

        $this->classroom = Classroom::factory()->for($this->west)->create(['title' => 'هفتم الف', 'mode' => 'online']);
        $this->classroom->onlineRooms()->create(['platform' => 'link', 'meeting_url' => 'https://meet.example.com/7a']);
        $members = app(ClassMembers::class);
        $members->add($this->classroom, ClassRole::factory()->for($this->primary)->teacher()->create(), [($this->teacher = $this->person())->id]);
        $members->add($this->classroom, ClassRole::factory()->for($this->primary)->attendee()->create(), [($this->student = $this->person())->id], CarbonImmutable::parse('2026-10-01'));

        app(SessionPlanner::class)->saveSchedule(null, $this->classroom, [
            'frequency' => Frequency::Weekly, 'interval' => 1, 'weekdays' => [Weekday::Saturday->value, Weekday::Monday->value],
            'starts_on' => '2026-10-31', 'ends_on' => '2026-11-30', 'start_time' => '08:00', 'end_time' => '09:30', 'teacher_ids' => [$this->teacher->id],
            'teaching_plan_id' => TeachingPlan::factory()->for($this->primary)->withTopics([1, 1])->create()->id,
        ]);
    }

    public function test_the_week_shows_the_persons_sessions_and_the_holidays(): void
    {
        $other = Classroom::factory()->for($this->west)->create();
        ClassSession::factory()->create(['classroom_id' => $other->id, 'starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-01 11:00']);
        Holiday::factory()->for($this->west)->create(['title' => 'اردو', 'starts_on' => '2026-11-04', 'ends_on' => '2026-11-04']);

        $this->travelTo(CarbonImmutable::parse('2026-10-31 08:30'));

        $this->as($this->student, route('my-classes.index', ['view' => 'week', 'date' => '2026-11-01']))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Class::Index')
            ->where('calendar.view', 'week')
            ->has('calendar.days', 7)
            ->where('calendar.days.0.date', '2026-10-31')
            ->where('calendar.days.0.sessions.0.class', 'هفتم الف')
            ->where('calendar.days.0.sessions.0.time', '08:00–09:30')
            ->where('calendar.days.0.sessions.0.tone', 'live')
            ->where('calendar.days.0.sessions.0.is_live', true)
            ->where('calendar.days.0.sessions.0.online_platform', 'لینک')
            ->where('calendar.days.1.sessions', [])
            ->where('calendar.days.2.sessions.0.class', 'هفتم الف')
            ->where('calendar.days.2.sessions.0.tone', 'scheduled')
            ->where('calendar.days.2.sessions.0.is_live', false)
            ->where('calendar.days.4.holiday', 'اردو')
            ->where('calendar.previous', '2026-10-24')
            ->has('classes', 1));
    }

    public function test_the_calendar_marks_past_unrecorded_sessions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 10:00'));

        $this->as($this->student, route('my-classes.index', ['view' => 'day', 'date' => '2026-10-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.days.0.sessions.0.tone', 'pending')
            ->where('calendar.days.0.sessions.0.tone_label', 'گذشته، ثبت‌نشده'));
    }

    public function test_the_default_jalali_month_shows_teaching_and_exam_sessions(): void
    {
        // Aban 1405: 2026-10-23 (a Friday) to 2026-11-21.
        $exam = ClassSession::factory()->for($this->classroom)->taughtBy($this->teacher)->create([
            'kind' => SessionKind::Exam,
            'starts_at' => '2026-10-31 10:00',
            'ends_at' => '2026-10-31 11:00',
        ]);
        $otherTenant = Classroom::factory()->for($this->primary)->create();
        ClassSession::factory()->for($otherTenant)->create([
            'kind' => SessionKind::Exam,
            'starts_at' => '2026-10-31 12:00',
            'ends_at' => '2026-10-31 13:00',
        ])->teachers()->attach($this->teacher);

        $this->as($this->teacher, '/profile/academic-calendar')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.view', 'month')
            ->where('calendar.days.0.date', '2026-10-23')
            ->has('calendar.days.8.sessions', 2)
            ->where('calendar.days.8.sessions.0.is_exam', false)
            ->where('calendar.days.8.sessions.1.id', $exam->id)
            ->where('calendar.days.8.sessions.1.is_exam', true)
            ->where('calendar.days.8.sessions.1.kind_label', 'امتحانی')
            ->has('calendar.days', 30)
            ->where('calendar.leading_blanks', 6)
            ->where('calendar.next', '2026-11-22'));
    }

    public function test_the_calendar_filters_by_class_subject_and_delivery_mode_together(): void
    {
        $math = Subject::factory()->for($this->primary)->create(['title' => 'ریاضی', 'is_active' => false, 'sort' => 1]);
        $science = Subject::factory()->for($this->primary)->create(['title' => 'علوم', 'sort' => 2]);
        $unrelated = Subject::factory()->for($this->primary)->create();
        $elsewhere = Subject::factory()->for($this->primary)->create();
        $onlineMath = $this->sessionOn('2026-10-31');
        $onlineMath->update(['subject_id' => $math->id, 'mode' => null]);
        ClassSession::factory()->for($this->classroom)->for($math)->create([
            'mode' => ClassMode::InPerson, 'starts_at' => '2026-10-31 10:00', 'ends_at' => '2026-10-31 11:00',
        ]);
        ClassSession::factory()->for($this->classroom)->for($science)->create([
            'mode' => ClassMode::Online, 'starts_at' => '2026-10-31 12:00', 'ends_at' => '2026-10-31 13:00',
        ]);
        $otherClass = Classroom::factory()->for($this->west)->create();
        ClassMember::factory()->for($otherClass)->for($this->student)
            ->for(ClassRole::factory()->for($this->primary)->attendee())->create();
        ClassSession::factory()->for($otherClass)->for($math)->create([
            'mode' => ClassMode::Online, 'starts_at' => '2026-10-31 14:00', 'ends_at' => '2026-10-31 15:00',
        ]);
        ClassSession::factory()->for(Classroom::factory()->for($this->west))->for($unrelated)->create();
        ClassSession::factory()->for(Classroom::factory()->for($this->primary))->for($elsewhere)->taughtBy($this->student)->create();

        $url = route('my-classes.index', [
            'view' => 'month', 'date' => '2026-10-28', 'classroom' => $this->classroom->id, 'subject' => $math->id, 'mode' => 'online',
        ]);

        $this->as($this->student, $url)->assertInertia(fn (AssertableInertia $page) => $page
            ->has('classes', 2)
            ->where('calendar.filters.classroom', $this->classroom->id)
            ->where('calendar.filters.subject', $math->id)
            ->where('calendar.filters.mode', 'online')
            ->where('calendar.filters.subjects', [
                ['value' => $math->id, 'label' => 'ریاضی'],
                ['value' => $science->id, 'label' => 'علوم'],
            ])
            ->where('calendar.filters.modes', [
                ['value' => 'in_person', 'label' => 'حضوری'],
                ['value' => 'online', 'label' => 'آنلاین'],
            ])
            ->has('calendar.days.8.sessions', 1)
            ->where('calendar.days.8.sessions.0.id', $onlineMath->id)
            ->where('calendar.days.8.sessions.0.subject', 'ریاضی')
            ->where('calendar.days.10.sessions', []));
    }

    public function test_the_calendar_ignores_a_class_filter_outside_the_current_branch(): void
    {
        $this->grantRole($this->student, $this->primary, 'member');
        $otherClass = Classroom::factory()->for($this->primary)->create();
        ClassMember::factory()->for($otherClass)->for($this->student)
            ->for(ClassRole::factory()->for($this->primary)->attendee())->create();
        ClassSession::factory()->for($otherClass)->create([
            'starts_at' => '2026-10-31 10:00', 'ends_at' => '2026-10-31 11:00',
        ]);

        $url = route('my-classes.index', ['view' => 'day', 'date' => '2026-10-31', 'classroom' => $otherClass->id]);

        $this->as($this->student, $url)->assertInertia(fn (AssertableInertia $page) => $page
            ->has('classes', 1)
            ->where('classes.0.id', $this->classroom->id)
            ->where('calendar.filters.classroom', null)
            ->has('calendar.days.0.sessions', 1)
            ->where('calendar.days.0.sessions.0.class', $this->classroom->title));
    }

    public function test_the_calendar_offers_only_memberships_in_the_class_filter(): void
    {
        $otherClass = Classroom::factory()->for($this->west)->create();
        $assignment = ClassSession::factory()->for($otherClass)->taughtBy($this->student)->create([
            'starts_at' => '2026-10-31 10:00', 'ends_at' => '2026-10-31 11:00',
        ]);

        $url = route('my-classes.index', ['view' => 'day', 'date' => '2026-10-31', 'classroom' => $otherClass->id]);

        $this->as($this->student, $url)->assertInertia(fn (AssertableInertia $page) => $page
            ->has('classes', 1)
            ->where('classes.0.id', $this->classroom->id)
            ->where('calendar.filters.classroom', null)
            ->has('calendar.days.0.sessions', 2)
            ->where('calendar.days.0.sessions.1.id', $assignment->id));
    }

    public function test_clearing_the_subject_filter_keeps_sessions_without_a_subject(): void
    {
        $session = $this->sessionOn('2026-10-31');
        $url = route('my-classes.index', ['view' => 'day', 'date' => '2026-10-31', 'mode' => 'online']);

        $this->as($this->student, $url)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.filters.subject', null)
            ->where('calendar.filters.mode', 'online')
            ->has('calendar.days.0.sessions', 1)
            ->where('calendar.days.0.sessions.0.id', $session->id)
            ->where('calendar.days.0.sessions.0.subject', null));
    }

    public function test_a_class_teacher_sees_only_the_sessions_they_are_assigned_to(): void
    {
        $assigned = $this->sessionOn('2026-10-31');
        ClassSession::factory()->for($this->classroom)->taughtBy($this->person())->create([
            'starts_at' => '2026-10-31 10:00',
            'ends_at' => '2026-10-31 11:00',
        ]);

        $this->as($this->teacher, route('my-classes.index', ['view' => 'day', 'date' => '2026-10-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('calendar.days.0.sessions', 1)
            ->where('calendar.days.0.sessions.0.id', $assigned->id));
    }

    public function test_the_calendar_shows_class_sessions_only_during_the_membership(): void
    {
        $this->classroom->members()->where('user_id', $this->student->id)->update([
            'joined_on' => '2026-11-02',
            'left_on' => '2026-11-02',
        ]);
        $duringMembership = $this->sessionOn('2026-11-02');
        ClassSession::factory()->for($this->classroom)->create([
            'starts_at' => '2026-11-03 10:00',
            'ends_at' => '2026-11-03 11:00',
        ]);

        $this->as($this->student, route('my-classes.index', ['view' => 'week', 'date' => '2026-11-02']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.days.0.sessions', [])
            ->has('calendar.days.2.sessions', 1)
            ->where('calendar.days.2.sessions.0.id', $duringMembership->id)
            ->where('calendar.days.3.sessions', []));
    }

    public function test_the_calendar_shows_the_session_to_its_assigned_substitute(): void
    {
        $teacher = $this->person();
        $substitute = $this->person();
        $session = ClassSession::factory()->for($this->classroom)->taughtBy($teacher, $substitute)->create([
            'starts_at' => '2026-10-31 10:00',
            'ends_at' => '2026-10-31 11:00',
        ]);
        $url = route('my-classes.index', ['view' => 'day', 'date' => '2026-10-31']);

        $this->as($substitute, $url)->assertInertia(fn (AssertableInertia $page) => $page
            ->has('calendar.days.0.sessions', 1)
            ->where('calendar.days.0.sessions.0.id', $session->id));
        $this->as($teacher, $url)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.days.0.sessions', []));
    }

    public function test_old_calendar_links_redirect_with_their_query_parameters(): void
    {
        $session = $this->sessionOn('2026-10-31');
        $this->as($this->student, '/profile/my-classes?view=week&date=2026-11-10')
            ->assertRedirect('/profile/academic-calendar?view=week&date=2026-11-10');
        $this->as($this->student, "/profile/my-classes/sessions/{$session->id}?for={$this->student->id}")
            ->assertRedirect("/profile/academic-calendar/sessions/{$session->id}?for={$this->student->id}");
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

        // A learner never takes attendance; the teacher may already (ADR 0070).
        $this->as($this->student, route('my-classes.attendance', $session), 'post', ['rows' => [['user_id' => $this->student->id, 'status' => 'present']]])
            ->assertSessionHasErrors('form');
        $this->as($this->teacher, route('my-classes.session', $session))->assertInertia(fn (AssertableInertia $page) => $page
            ->whereNot('attendance', null)
            ->whereNot('contentForm', null));

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

    public function test_staff_can_leave_a_reminder_for_a_later_session_and_read_it_there(): void
    {
        $source = $this->sessionOn('2026-10-31');
        $target = $this->sessionOn('2026-11-02');
        $subject = Subject::factory()->for($this->primary)->create();
        $source->update(['subject_id' => $subject->id]);
        $target->update(['subject_id' => $subject->id]);
        $supporter = $this->person();
        app(ClassMembers::class)->add($this->classroom, ClassRole::factory()->for($this->primary)->create(['title' => 'پشتیبان']), [$supporter->id]);
        $this->travelTo(CarbonImmutable::parse('2026-10-31 08:15'));
        $url = route('my-classes.reminders.store', ['session' => $source, 'target' => $target]);

        $this->as($supporter, $url, 'post', ['body' => '  '])->assertSessionHasErrors('body');
        $this->as($supporter, $url, 'post', ['body' => 'تمرین فصل دوم مرور شود.'])
            ->assertRedirect(route('my-classes.session', $source))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('class_session_reminders', [
            'tenant_id' => $this->west->id,
            'session_id' => $target->id,
            'source_session_id' => $source->id,
            'author_id' => $supporter->id,
            'body' => 'تمرین فصل دوم مرور شود.',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:15'));
        $this->as($this->teacher, route('my-classes.session', $target))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reminderTimeline.sessions.0.id', $target->id)
            ->where('reminderTimeline.sessions.0.is_current', true)
            ->where('reminderTimeline.sessions.0.reminders.0.body', 'تمرین فصل دوم مرور شود.')
            ->where('reminderTimeline.sessions.0.reminders.0.author', $supporter->name));
        $this->as($this->student, route('my-classes.session', $target))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reminderTimeline', null));
        $this->as($this->teacher, $url, 'post', ['body' => 'پس از شروع'])->assertNotFound();
    }

    public function test_the_reminder_timeline_and_posts_are_scoped_to_class_subject_and_staff_access(): void
    {
        $term = Term::factory()->for($this->primary)->create();
        $subject = Subject::factory()->for($this->primary)->create();
        $this->classroom->update(['term_id' => $term->id]);
        $source = $this->sessionOn('2026-10-31');
        $source->update(['subject_id' => $subject->id]);
        $sameTerm = Classroom::factory()->for($this->west)->for($term)->create();
        $otherTerm = Classroom::factory()->for($this->west)->for(Term::factory()->for($this->primary))->create();
        $otherBranch = Classroom::factory()->for($this->primary)->for($term)->create();
        $later = ClassSession::factory()->for($subject)->state(['starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-01 11:00']);
        $allowed = $later->for($this->classroom)->taughtBy($this->person(), $this->teacher)->create();
        $wrongClass = $later->for($sameTerm)->taughtBy($this->teacher)->create();
        $wrongTerm = $later->for($otherTerm)->taughtBy($this->teacher)->create();
        $wrongBranch = $later->for($otherBranch)->taughtBy($this->teacher)->create();
        $wrongSubject = $later->for($this->classroom)->for(Subject::factory()->for($this->primary))->create();
        $notStaff = $later->for($sameTerm)->create();
        $cancelled = $later->for($this->classroom)->create(['status' => SessionStatus::Cancelled]);

        $this->as($this->teacher, route('my-classes.session', $source))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reminderTimeline.term', $term->title)
            ->has('reminderTimeline.sessions', 2)
            ->where('reminderTimeline.sessions.0.id', $source->id)
            ->where('reminderTimeline.sessions.1.id', $allowed->id));

        foreach ([$wrongClass, $wrongTerm, $wrongBranch, $wrongSubject, $notStaff, $cancelled, $source] as $target) {
            $url = route('my-classes.reminders.store', ['session' => $source, 'target' => $target]);
            $this->as($this->teacher, $url, 'post', ['body' => 'این یادآوری نباید ذخیره شود.'])->assertNotFound();
        }
        $url = route('my-classes.reminders.store', ['session' => $source, 'target' => $allowed]);
        $this->as($this->student, $url, 'post', ['body' => 'یادآوری فراگیر'])->assertNotFound();
        $this->as($this->teacher, $url.'?for='.$this->student->id, 'post', ['body' => 'یادآوری در نمای سرپرست'])->assertNotFound();
        $this->assertDatabaseCount('class_session_reminders', 0);
    }

    public function test_the_menu_link_is_shown_to_class_members_only(): void
    {
        $hasLink = fn (User $user): bool => collect($this->as($user, route('profile.edit'))->viewData('page')['props']['navigation'])->contains('title', 'تقویم آموزشی');

        $this->assertTrue($hasLink($this->student));
        $this->assertFalse($hasLink($this->person()));
    }

    public function test_the_menu_shows_the_current_organization_term_in_a_branch(): void
    {
        $url = route('profile.edit');
        $this->as($this->student, $url)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('navigationSubtitle', null));

        Term::factory()->for($this->primary)->create(['starts_on' => '2026-09-01']);
        $current = Term::factory()->for($this->primary)->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-29']);
        Term::factory()->for($this->primary)->create(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-28']);
        Term::factory()->for($this->primary)->create(['starts_on' => '2026-10-15', 'is_active' => false]);
        Term::factory()->for($this->primary)->create(['starts_on' => '2026-11-01']);
        Term::factory()->for($this->west)->create(['starts_on' => '2026-10-20']);

        $this->as($this->student, $url)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tenant.current.id', $this->west->id)
            ->where('navigationSubtitle', $current->title));
    }

    /**
     * ADR 0069: a teacher (or anyone in classes) opens the panel on their
     * calendar; others stay on the dashboard.
     */
    public function test_the_panel_opens_on_the_calendar_for_people_in_classes(): void
    {
        $this->as($this->teacher, route('dashboard'))->assertRedirect(route('my-classes.index'));
        $this->as($this->teacher, route('my-classes.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Class::Index')
            ->where('panelHome', route('my-classes.index'))
            ->where('navigation.0', ['title' => 'تقویم آموزشی', 'url' => route('my-classes.index'), 'icon' => 'calendar']));

        $this->as($this->person(), route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('panelHome', null));
    }

    public function test_logging_in_as_a_teacher_lands_on_the_calendar(): void
    {
        $this->teacher->forceFill(['password' => 'secret-pass'])->save();

        $this->withSession([ResolveCurrentTenant::SESSION_KEY => $this->west->id])
            ->post(route('login'), ['identifier' => $this->teacher->email, 'password' => 'secret-pass'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertRedirect(route('my-classes.index'));
    }

    public function test_a_guardian_sees_the_wards_classes_read_only(): void
    {
        $father = $this->person();
        $student = $this->student;
        Guardianship::query()->create(['guardian_id' => $father->id, 'ward_id' => $student->id, 'relationship' => 'father']);
        $session = $this->sessionOn('2026-10-31');
        Attendance::factory()->create(['session_id' => $session->id, 'user_id' => $student->id, 'status' => AttendanceStatus::Absent]);

        $nav = collect($this->as($father, route('dashboard'))->viewData('page')['props']['navigation']);
        $this->assertFalse($nav->contains('title', 'تقویم آموزشی'));
        $this->assertTrue($nav->contains('url', route('my-classes.index', ['for' => $student->id])));

        $calendar = route('my-classes.index', [
            'for' => $student->id, 'view' => 'day', 'date' => '2026-10-31', 'classroom' => $this->classroom->id,
        ]);
        $this->as($father, $calendar)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ward.id', $student->id)
            ->where('calendar.filters.classroom', $this->classroom->id)
            ->where('calendar.days.0.sessions.0.my_attendance', 'غایب'));

        // Even while the session is open, a guardian gets no forms.
        $this->travelTo(CarbonImmutable::parse('2026-10-31 08:05'));
        $this->teacher->wards()->attach($student->id, ['relationship' => 'other']);
        $this->as($this->teacher, route('my-classes.session', ['session' => $session, 'for' => $student->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('ward.name', $student->name)
            ->where('myAttendance.status', 'غایب')
            ->where('reminderTimeline', null)
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

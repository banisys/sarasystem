<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Enums\Weekday;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\Classrooms\Pages\ClassSessions;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SessionsRelationManager;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ListClassSessions;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ViewClassSession;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ViewExamSession;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Models\Subject;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\SessionCalendar;
use Modules\Class\Services\SessionPlanner;
use Modules\Employee\Models\Employee;
use Modules\Exam\Filament\Resources\Exams\ExamResource;
use Modules\Exam\Filament\Resources\Exams\Pages\CreateContextExam;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamGroup;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Schedules, the sessions built from them, and changing sessions (ADR 0023).
 * Today is Thursday 2026-10-29 (1405/08/07); 2026-10-31 is a Saturday.
 */
class ClassSchedulesTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private Classroom $classroom;

    private User $teacher;

    private SessionPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();

        $this->roleWithPermissions('teacher', []);
        $this->classroom = Classroom::factory()->for($this->west)->create(['location' => 'کلاس ۱۰۱']);
        $this->teacher = $this->teacherOf($this->classroom);
        $this->planner = app(SessionPlanner::class);
    }

    public function test_a_weekly_schedule_builds_past_and_future_sessions_without_holidays(): void
    {
        Holiday::factory()->for($this->primary)->create(['starts_on' => '2026-11-07', 'ends_on' => '2026-11-07']);
        Holiday::factory()->for($this->west)->create(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-09']);
        Holiday::factory()->for(Tenant::factory()->create())->create(['starts_on' => '2026-11-14', 'ends_on' => '2026-11-14']);

        // From Monday 10-26: past meetings are built too.
        $this->weekly([Weekday::Saturday, Weekday::Monday, Weekday::Wednesday], '2026-10-26', '2026-11-16');

        $this->assertSame(
            ['2026-10-26', '2026-10-28', '2026-10-31', '2026-11-02', '2026-11-04', '2026-11-11', '2026-11-14', '2026-11-16'],
            $this->sessionDays(),
        );
        $session = ClassSession::query()->orderBy('starts_at')->first();
        $this->assertSame('08:00', $session->starts_at->format('H:i'));
        $this->assertSame('09:30', $session->ends_at->format('H:i'));
        $this->assertSame([$this->teacher->id], $session->teachers->modelKeys());
        $this->assertSame('کلاس ۱۰۱', $session->location);
        $this->assertSame(SessionKind::Regular, $session->kind);
    }

    public function test_a_once_schedule_builds_a_session_that_has_already_started(): void
    {
        $data = $this->data([], '2026-10-29', '2026-10-29', '11:59', '17:00');
        $data['frequency'] = Frequency::Once;

        $schedule = $this->planner->saveSchedule(null, $this->classroom, $data);

        $session = $schedule->sessions()->sole();
        $this->assertSame('2026-10-29 11:59:00', $session->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-29 17:00:00', $session->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_rebuilding_preserves_existing_past_sessions_without_duplicating_them(): void
    {
        $schedule = $this->weekly([Weekday::Monday, Weekday::Wednesday], '2026-10-26', '2026-11-04');
        $past = $schedule->sessions()->whereDate('starts_at', '2026-10-26')->sole();
        $past->update(['status' => SessionStatus::Held, 'summary' => 'درس ثبت‌شده']);
        $pastIds = $schedule->sessions()->where('starts_at', '<=', now())->pluck('id')->all();

        $this->planner->saveSchedule($schedule, $this->classroom, [
            ...$this->data([Weekday::Monday, Weekday::Wednesday], '2026-10-26', '2026-11-04', '10:00', '11:00'),
            'mode' => ClassMode::Online,
        ]);
        $this->planner->sync($schedule->fresh());

        $this->assertSame(['2026-10-26', '2026-10-28', '2026-11-02', '2026-11-04'], $this->sessionDays());
        $this->assertSame($pastIds, $schedule->sessions()->where('starts_at', '<=', now())->pluck('id')->all());
        $this->assertSame('08:00', $past->fresh()->starts_at->format('H:i'));
        $this->assertSame(SessionStatus::Held, $past->fresh()->status);
        $this->assertSame('درس ثبت‌شده', $past->fresh()->summary);
        $this->assertSame(ClassMode::InPerson, $past->fresh()->mode);
        $future = $schedule->sessions()->whereDate('starts_at', '2026-11-02')->sole();
        $this->assertSame('10:00', $future->starts_at->format('H:i'));
        $this->assertSame(ClassMode::Online, $future->mode);
        $this->assertNull($future->location);
    }

    public function test_editing_a_schedule_rebuilds_only_untouched_future_sessions(): void
    {
        $schedule = $this->weekly([Weekday::Saturday, Weekday::Monday], '2026-10-31', '2026-11-16');
        [$moved, $cancelled, $substituted] = ClassSession::query()->orderBy('starts_at')->take(3)->get()->all();
        $this->planner->move($moved, '2026-10-31', '14:00', '15:00', null);
        $this->planner->cancel($cancelled, 'بیماری مدرس');
        $this->planner->setSubstitute($substituted, $this->teacher->id, $this->teacherOf($this->classroom)->id);

        $this->planner->saveSchedule($schedule, $this->classroom, $this->data([Weekday::Sunday], '2026-10-31', '2026-11-16', '10:00', '11:00'));

        // Kept: the moved (10-31) and substituted (11-07) ones; cancelled 11-02 is no longer a meeting day.
        $this->assertSame(['2026-10-31', '2026-11-01', '2026-11-07', '2026-11-08', '2026-11-15'], $this->sessionDays());
        $this->assertSame('14:00', $moved->fresh()->starts_at->format('H:i'));
        $this->assertNull($cancelled->fresh());
        $this->assertSame('08:00', $substituted->fresh()->starts_at->format('H:i'));
        $this->assertSame('10:00', ClassSession::query()->whereDate('starts_at', '2026-11-01')->sole()->starts_at->format('H:i'));
    }

    public function test_deleting_a_schedule_keeps_past_and_changed_sessions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00'));
        $schedule = $this->weekly([Weekday::Saturday], '2026-10-24', '2026-11-21');
        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));
        $changed = ClassSession::query()->whereDate('starts_at', '2026-11-07')->sole();
        $this->planner->cancel($changed, 'اردو');

        $this->planner->deleteSchedule($schedule);

        $this->assertSame(['2026-10-24', '2026-11-07'], $this->sessionDays());
        $this->assertNull($changed->fresh()->schedule_id);
    }

    public function test_a_deleted_session_does_not_come_back_when_its_schedule_is_rebuilt(): void
    {
        $schedule = $this->weekly([Weekday::Saturday], '2026-10-31', '2026-11-21');
        $this->planner->deleteSession(ClassSession::query()->whereDate('starts_at', '2026-11-07')->sole());

        $this->planner->saveSchedule($schedule->fresh(), $this->classroom, $this->data([Weekday::Saturday], '2026-10-31', '2026-11-21', '10:00', '11:00'));
        $holiday = Holiday::factory()->for($this->primary)->create(['starts_on' => '2026-11-07', 'ends_on' => '2026-11-07']);
        $holiday->delete();

        $this->assertSame(['2026-10-31', '2026-11-14', '2026-11-21'], $this->sessionDays());
        $this->assertSame('10:00', ClassSession::query()->whereDate('starts_at', '2026-10-31')->sole()->starts_at->format('H:i'));
    }

    public function test_deleting_a_moved_session_skips_its_original_day(): void
    {
        $schedule = $this->weekly([Weekday::Saturday], '2026-10-31', '2026-11-14');
        $session = ClassSession::query()->whereDate('starts_at', '2026-11-07')->sole();
        $this->planner->move($session, '2026-11-08', '14:00', '15:00', null);

        $this->planner->deleteSession($session->fresh());
        $this->planner->sync($schedule->fresh());

        $this->assertSame(['2026-10-31', '2026-11-14'], $this->sessionDays());
    }

    public function test_a_session_that_was_held_or_started_or_has_content_cannot_be_deleted(): void
    {
        $this->weekly([Weekday::Saturday], '2026-10-31', '2026-11-21');
        [$held, $started, $withContent, $cancelled] = ClassSession::query()->orderBy('starts_at')->get()->all();
        $held->update(['status' => SessionStatus::Held]);
        $started->update(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
        $withContent->update(['status' => SessionStatus::Cancelled, 'content_recorded_at' => now()]);
        $this->planner->cancel($cancelled, 'اردو');

        foreach ([$held, $started, $withContent] as $session) {
            try {
                $this->planner->deleteSession($session->fresh());
                $this->fail('The session should not have been deleted.');
            } catch (ClassException) {
                $this->assertNotNull($session->fresh());
            }
        }

        $this->planner->deleteSession($cancelled->fresh());
        $this->assertNull($cancelled->fresh());
    }

    public function test_a_past_session_with_nothing_recorded_can_be_deleted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00'));
        $schedule = $this->weekly([Weekday::Saturday], '2026-10-24', '2026-11-07');
        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));

        $this->planner->deleteSession(ClassSession::query()->whereDate('starts_at', '2026-10-24')->sole());
        $this->planner->sync($schedule->fresh());

        $this->assertSame(['2026-10-31', '2026-11-07'], $this->sessionDays());
    }

    public function test_a_new_holiday_removes_its_sessions_and_brings_them_back_when_deleted(): void
    {
        $this->weekly([Weekday::Saturday], '2026-10-31', '2026-11-21');

        $holiday = Holiday::factory()->for($this->primary)->create(['starts_on' => '2026-11-06', 'ends_on' => '2026-11-14']);
        $this->assertSame(['2026-10-31', '2026-11-21'], $this->sessionDays());

        $holiday->delete();
        $this->assertSame(['2026-10-31', '2026-11-07', '2026-11-14', '2026-11-21'], $this->sessionDays());
    }

    public function test_the_teacher_class_and_place_must_be_free(): void
    {
        $this->weekly([Weekday::Saturday], '2026-10-31', '2026-11-21');

        // The same teacher in another tenant at an overlapping time.
        $elsewhere = Classroom::factory()->for($this->primary)->create(['title' => 'کلاس ستاد']);
        $this->primary->execute(fn () => app(ClassMembers::class)->add($elsewhere, $this->teacherRole(), [$this->grantTeacher($this->teacher, $this->primary)->id]));
        $this->assertClash(fn () => $this->primary->execute(fn () => $this->planner->saveSchedule(null, $elsewhere, $this->data([Weekday::Saturday], '2026-11-07', '2026-11-07', '09:00', '10:00', $this->teacher->id))), 'مدرس «'.$this->teacher->name.'» در «'.$this->classroom->title);

        // The same class.
        $this->assertClash(fn () => $this->weekly([Weekday::Saturday], '2026-11-14', '2026-11-14', teacher: null, fromTime: '09:00', toTime: '10:00'), 'همین کلاس');

        // The same place in the tenant (another class).
        $other = Classroom::factory()->for($this->west)->create(['location' => 'کلاس ۱۰۱']);
        $this->assertClash(fn () => $this->planner->saveSchedule(null, $other, $this->data([Weekday::Saturday], '2026-11-21', '2026-11-21', '08:30', '09:00')), 'مکان');

        // Not at another time, nor against a cancelled session.
        $this->planner->cancel(ClassSession::query()->whereDate('starts_at', '2026-11-21')->sole(), 'تعطیل');
        $this->planner->saveSchedule(null, $other, $this->data([Weekday::Saturday], '2026-11-21', '2026-11-28', '08:30', '09:00'));
        $this->assertSame(2, $other->sessions()->count());
    }

    public function test_the_teacher_is_a_member_who_is_not_an_attendee(): void
    {
        $outsider = User::factory()->create();
        $this->grantRole($outsider, $this->west, 'teacher');

        $this->assertClash(fn () => $this->weekly([Weekday::Saturday], '2026-10-31', '2026-11-21', teacher: $outsider->id), 'مدرس باید عضو کلاس');
    }

    public function test_moving_a_session_keeps_its_original_time_and_checks_clashes(): void
    {
        $this->weekly([Weekday::Saturday, Weekday::Monday], '2026-10-31', '2026-11-09');
        $saturday = ClassSession::query()->whereDate('starts_at', '2026-10-31')->sole();

        $this->assertClash(fn () => $this->planner->move($saturday, '2026-11-02', '09:00', '10:00', null), 'همین کلاس');

        $this->planner->move($saturday, '2026-11-01', '09:00', '10:00', 'سالن');
        $saturday->refresh();
        $this->assertTrue($saturday->wasMoved());
        $this->assertSame('2026-10-31 08:00', $saturday->original_starts_at->format('Y-m-d H:i'));
        $this->assertSame('سالن', $saturday->location);
        $this->assertFalse($saturday->isUntouched());
    }

    public function test_the_tabs_add_a_schedule_and_cancel_a_session_for_whoever_may_edit_the_class(): void
    {
        $editor = User::factory()->create();
        $this->roleWithPermissions('class_editor', ['ViewAny:Classroom', 'View:Classroom', 'Update:Classroom']);
        $this->makeStaff($editor, $this->west);
        $this->grantRole($editor, $this->west, 'class_editor');
        $viewer = User::factory()->create();
        $this->roleWithPermissions('class_viewer', ['ViewAny:Classroom', 'View:Classroom']);
        $this->makeStaff($viewer, $this->west);
        $this->grantRole($viewer, $this->west, 'class_viewer');
        $owner = ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class];

        $this->actingInPanel($viewer, $this->west);
        Livewire::test(SessionsRelationManager::class, $owner)
            ->assertActionHidden('addSchedule')
            ->assertActionHidden('addSession');

        $this->actingInPanel($editor, $this->west);
        Livewire::test(SessionsRelationManager::class, $owner)
            ->callAction('addSchedule', data: [
                'frequency' => Frequency::Weekly->value, 'interval' => 1, 'weekdays' => [Weekday::Sunday->value, Weekday::Tuesday->value],
                'mode' => ClassMode::Online->value,
                'starts_on' => '2026-11-01', 'ends_on' => '2026-11-10', 'start_time' => '10:00', 'end_time' => '11:30', 'teacher_ids' => [$this->teacher->id],
            ])
            ->assertHasNoActionErrors();
        $this->assertSame(['2026-11-01', '2026-11-03', '2026-11-08', '2026-11-10'], $this->sessionDays());

        $first = ClassSession::query()->orderBy('starts_at')->first();
        $this->assertSame(ClassMode::Online, $first->schedule->mode);
        $this->assertSame(ClassMode::Online, $first->mode);
        $this->assertNull($first->location);
        Livewire::test(SessionsRelationManager::class, $owner)
            ->callAction(TestAction::make('cancelSession')->table($first), data: ['reason' => 'جلسه‌ی اولیا'])
            ->assertHasNoActionErrors()
            ->assertActionHasLabel('addSession', 'جلسه‌ی غیر درسی')
            ->mountAction('addSession')
            ->assertFormFieldDoesNotExist('kind', 'mountedActionSchema0')
            ->assertFormFieldDoesNotExist('subject_id', 'mountedActionSchema0')
            ->fillForm([
                'mode' => ClassMode::InPerson->value, 'date' => '2026-11-05', 'start_time' => '10:00', 'end_time' => '11:00',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(SessionStatus::Cancelled, $first->fresh()->status);
        $session = $this->classroom->sessions()->whereDate('starts_at', '2026-11-05')->sole();
        $this->assertSame(SessionKind::NonInstructional, $session->kind);
        $this->assertNull($session->subject_id);
        $this->assertSame(ClassMode::InPerson, $session->mode);
        $this->assertSame('کلاس ۱۰۱', $session->location);

        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->mountAction('editSession')
            ->assertFormFieldIsHidden('subject_id', 'mountedActionSchema0');
    }

    public function test_a_teaching_session_can_edit_its_details_without_changing_its_schedule_or_time(): void
    {
        $schedule = $this->weekly([Weekday::Saturday], '2026-10-31', '2026-11-14');
        $session = $schedule->sessions()->whereDate('starts_at', '2026-11-07')->sole();
        $session->update(['mode' => ClassMode::Online, 'location' => null]);
        $subject = Subject::factory()->for($this->primary)->create(['is_active' => true]);
        $before = $schedule->fresh()->getAttributes();
        $other = $schedule->sessions()->whereKeyNot($session->id)->firstOrFail();
        $otherBefore = $other->getAttributes();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->west);

        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->assertActionDoesNotExist('editSchedule')
            ->assertSee('ویرایش جلسه')
            ->callAction('editSession', data: [
                'subject_id' => $subject->id,
                'mode' => ClassMode::InPerson->value,
                'location' => 'اتاق دوم',
                'note' => 'توضیح این جلسه',
                'title' => 'مرور فصل اول',
                'starts_at' => '2026-11-07 12:00:00',
                'ends_at' => '2026-11-07 13:00:00',
            ])
            ->assertHasNoActionErrors();

        $updated = $session->fresh();
        $this->assertSame(ClassMode::InPerson, $updated->mode);
        $this->assertSame($subject->id, $updated->subject_id);
        $this->assertSame('اتاق دوم', $updated->location);
        $this->assertSame('توضیح این جلسه', $updated->note);
        $this->assertSame('مرور فصل اول', $updated->title);
        $this->assertSame('مرور فصل اول', app(SessionCalendar::class)->card($updated)['title']);
        $this->assertTrue($session->starts_at->equalTo($updated->starts_at));
        $this->assertTrue($session->ends_at->equalTo($updated->ends_at));
        $this->assertSame($before, $schedule->fresh()->getAttributes());
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
    }

    public function test_a_refused_schedule_keeps_its_modal_open_with_the_message(): void
    {
        $editor = User::factory()->create();
        $this->roleWithPermissions('class_editor', ['ViewAny:Classroom', 'View:Classroom', 'Update:Classroom']);
        $this->makeStaff($editor, $this->west);
        $this->grantRole($editor, $this->west, 'class_editor');
        $this->weekly([Weekday::Saturday], '2026-10-31', '2026-11-21');
        $this->actingInPanel($editor, $this->west);

        Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class])
            ->callAction('addSchedule', data: [
                'frequency' => Frequency::Weekly->value, 'interval' => 1, 'weekdays' => [Weekday::Saturday->value],
                'starts_on' => '2026-10-31', 'ends_on' => '2026-11-07', 'start_time' => '08:30', 'end_time' => '09:30',
            ])
            ->assertNotified()
            ->assertActionMounted('addSchedule')
            ->assertSchemaStateSet(['start_time' => '08:30', 'end_time' => '09:30'], 'mountedActionSchema0');

        $this->assertSame(1, $this->classroom->schedules()->count());
    }

    public function test_the_exam_schedule_modal_creates_separate_sessions_for_the_class(): void
    {
        $editor = User::factory()->superAdmin()->create();
        $subject = Subject::factory()->for($this->primary)->create();
        $this->actingInPanel($editor, $this->west);
        $action = 'addExamSchedule';

        $this->get(ClassroomResource::getUrl('sessions', ['record' => $this->classroom], tenant: $this->west))
            ->assertOk()
            ->assertSee($this->classroom->title);

        $calendar = Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class])
            ->assertActionHasLabel($action, 'برنامه امتحانی')
            ->mountAction($action)
            ->assertActionMounted($action)
            ->fillForm(['sessions' => [
                ['date' => '2026-11-05', 'start_time' => '10:00', 'end_time' => '11:00', 'mode' => ClassMode::Online->value, 'subject_id' => $subject->id, 'note' => 'آزمون پایان دوره'],
                ['date' => '2026-11-08', 'start_time' => '13:30', 'end_time' => '15:00', 'mode' => ClassMode::InPerson->value, 'location' => 'سالن آزمون'],
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('جلسه‌های امتحانی ثبت شدند.');

        [$first, $second] = $this->classroom->sessions()->orderBy('starts_at')->get()->all();
        $this->assertSame(['2026-11-05', '2026-11-08'], $this->sessionDays());
        $this->assertSame(SessionKind::Exam, $first->kind);
        $this->assertSame(SessionKind::Exam, $second->kind);
        $this->assertNull($first->schedule_id);
        $this->assertSame($subject->id, $first->subject_id);
        $this->assertSame(ClassMode::Online, $first->mode);
        $this->assertNull($first->location);
        $this->assertSame('آزمون پایان دوره', $first->note);
        $this->assertSame('13:30–15:00', $second->starts_at->format('H:i').'–'.$second->ends_at->format('H:i'));
        $this->assertSame(ClassMode::InPerson, $second->mode);
        $this->assertSame('سالن آزمون', $second->location);
        $this->assertFalse($this->classroom->schedules()->exists());
        $calendar
            ->call('showSessions', 'day', '2026-11-08')
            ->assertSeeHtml('title="حضوری"')
            ->assertSee('امتحانی')
            ->call('showSessions', 'day', '2026-11-05')
            ->assertSeeHtml('title="آنلاین"')
            ->assertSee('امتحانی');
    }

    public function test_old_schedule_links_redirect_to_the_class_calendar(): void
    {
        $viewer = User::factory()->create();
        $this->roleWithPermissions('class_viewer', ['ViewAny:Classroom', 'View:Classroom']);
        $this->makeStaff($viewer, $this->west);
        $this->grantRole($viewer, $this->west, 'class_viewer');
        $past = ClassSession::factory()->for($this->classroom)->taughtBy($this->teacher)->create([
            'kind' => SessionKind::Exam,
            'starts_at' => '2026-10-28 10:00:00',
            'ends_at' => '2026-10-28 11:00:00',
            'location' => 'سالن امتحان',
        ]);
        $foreignClass = Classroom::factory()->for($this->primary)->create();
        $foreignExam = ClassSession::factory()->for($foreignClass)->create([
            'kind' => SessionKind::Exam,
            'starts_at' => '2026-10-28 10:00:00',
            'ends_at' => '2026-10-28 11:00:00',
        ]);
        $this->actingInPanel($viewer, $this->west);

        Livewire::withoutLazyLoading();
        $teachingUrl = ClassroomResource::getUrl('schedules', ['record' => $this->classroom], tenant: $this->west);
        $calendarUrl = ClassroomResource::getUrl('sessions', ['record' => $this->classroom], tenant: $this->west);
        $this->get(ClassroomResource::getUrl('index', tenant: $this->west))
            ->assertOk()
            ->assertDontSee($teachingUrl)
            ->assertSee($calendarUrl)
            ->assertDontSee('برنامهٔ امتحانی');
        $this->get($teachingUrl)->assertRedirect($calendarUrl);
        $this->get(ClassroomResource::getUrl('schedules', ['record' => $foreignClass], tenant: $this->west))->assertNotFound();
        $this->get($calendarUrl)->assertOk()->assertSee('برنامه‌های درسی');
        $this->get(ClassroomResource::getUrl('exam-schedules', ['record' => $this->classroom], tenant: $this->west))
            ->assertRedirect($calendarUrl);
        $this->get(ClassroomResource::getUrl('exam-schedules', ['record' => $foreignClass], tenant: $this->west))
            ->assertNotFound();

        Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class])
            ->call('showSessions', 'day', '2026-10-28')
            ->assertSee('امتحانی')
            ->assertSee(ClassSessionResource::detailsUrl($past, $this->west))
            ->assertDontSee(ClassSessionResource::detailsUrl($foreignExam, $this->west));
    }

    public function test_the_exam_session_opens_a_create_page_that_saves_its_exam(): void
    {
        if (! Module::isEnabled('Exam')) {
            $this->markTestSkipped('Exam is disabled.');
        }

        $admin = User::factory()->superAdmin()->create();
        $this->actingInPanel($admin, $this->west);
        $session = ClassSession::factory()->for($this->classroom)->create(['kind' => SessionKind::Exam]);
        $bank = QuestionBank::factory()->for($this->west)->create();
        $group = ExamGroup::factory()->for($this->west)->create(['title' => 'خرداد ۱۴۰۵']);
        $attendee = ClassMember::factory()->for($this->classroom)->for(ClassRole::factory()->create(['is_attendee' => true]))->create();
        $outsider = User::factory()->create();
        $params = ['contextType' => 'class-session', 'contextId' => $session->id];
        $url = ExamResource::getUrl('create-for-context', $params, tenant: $this->west);
        $returnUrl = ClassSessionResource::detailsUrl($session, $this->west);

        Livewire::test(ViewExamSession::class, ['record' => $session->id])
            ->assertActionHasUrl('sessionExam', $url);
        $this->get($url)->assertOk()->assertSee(['ساخت آزمون جلسه', $this->classroom->title, $bank->title, $group->title]);

        Livewire::test(CreateContextExam::class, $params)
            ->assertFormFieldIsHidden('is_practice')
            ->assertFormFieldIsHidden('starts_at')
            ->assertFormFieldIsHidden('ends_at')
            ->assertFormFieldIsHidden('participantUsers')
            ->fillForm([
                'title' => 'آزمون این جلسه',
                'exam_group_id' => $group->id,
                'is_published' => true,
                'sections' => [['question_bank_id' => $bank->id, 'question_count' => 3]],
                'duration_minutes' => 40,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect($returnUrl);

        $exam = Exam::query()->sole();
        $this->assertFalse($exam->is_practice);
        $this->assertFalse($exam->allow_late_start);
        $this->assertSame('آزمون این جلسه', $exam->title);
        $this->assertSame($group->id, $exam->exam_group_id);
        $this->assertSame($this->west->id, $exam->tenant_id);
        $this->assertSame('class-session', $exam->context_type);
        $this->assertSame($session->id, $exam->context_id);
        $this->assertSame($admin->id, $exam->assigned_by_id);
        $this->assertNull($exam->template_exam_id);
        $this->assertTrue($session->starts_at->eq($exam->starts_at));
        $this->assertTrue($session->ends_at->eq($exam->ends_at));
        $this->assertTrue($exam->is_published);
        $this->assertSame(40, $exam->duration_minutes);
        $this->assertSame($bank->id, $exam->sections()->sole()->question_bank_id);
        $this->assertSame(3, $exam->questionCount());
        $this->assertTrue($exam->hasParticipant($attendee->user));
        $this->assertFalse($exam->hasParticipant($outsider));

        $this->get($url)->assertRedirect(ClassSessionResource::detailsUrl($session, $this->west));
        $editUrl = ExamResource::getUrl('edit', ['record' => $exam], tenant: $this->west);
        Livewire::test(ViewExamSession::class, ['record' => $session->id])
            ->assertActionHasUrl('sessionExam', $editUrl)
            ->assertActionHasIcon('sessionExam', Heroicon::OutlinedDocumentText);
        $this->get($editUrl)->assertOk()->assertSee($exam->title);

        $exam->forceFill(['user_panel_locked' => true])->save();
        Livewire::test(ViewExamSession::class, ['record' => $session->id])
            ->assertActionHasUrl('sessionExam', $editUrl)
            ->assertActionHasIcon('sessionExam', Heroicon::OutlinedLockClosed)
            ->assertSee('آزمون جلسه، قفل‌شده در پنل کاربری');
    }

    public function test_an_already_open_admin_form_cannot_create_a_second_exam(): void
    {
        if (! Module::isEnabled('Exam')) {
            $this->markTestSkipped('Exam is disabled.');
        }

        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->west);
        $session = ClassSession::factory()->for($this->classroom)->create(['kind' => SessionKind::Exam]);
        $bank = QuestionBank::factory()->for($this->west)->create();
        $draft = Livewire::test(CreateContextExam::class, ['contextType' => 'class-session', 'contextId' => $session->id])
            ->fillForm(['title' => 'دومین آزمون', 'sections' => [['question_bank_id' => $bank->id, 'question_count' => 1]]]);
        $exam = Exam::factory()->for($this->west)->create();
        $exam->forceFill(['context_type' => 'class-session', 'context_id' => $session->id])->save();

        $draft->call('create')->assertHasFormErrors(['title']);

        $this->assertDatabaseCount('exams', 1);
    }

    public function test_the_exam_create_page_opens_a_printable_preview_of_its_unsaved_questions(): void
    {
        if (! Module::isEnabled('Exam')) {
            $this->markTestSkipped('Exam is disabled.');
        }

        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->west);
        $session = ClassSession::factory()->for($this->classroom)->create(['kind' => SessionKind::Exam]);
        $bank = QuestionBank::factory()->for($this->west)->create();
        $question = Question::factory()->for($this->west)->descriptive()->create([
            'body' => 'متن سؤال نمونه', 'model_answer' => 'پاسخ مخصوص تصحیح',
        ]);
        $choice = Question::factory()->for($this->west)->create(['body' => 'یک سؤال چندگزینه‌ای']);
        $inactive = Question::factory()->for($this->west)->inactive()->create(['body' => 'متن سؤال غیرفعال']);
        $bank->questions()->attach([$question->id, $choice->id, $inactive->id]);

        Livewire::test(CreateContextExam::class, ['contextType' => 'class-session', 'contextId' => $session->id])
            ->assertSee('پیش‌نمایش آزمون جلسه')
            ->fillForm([
                'title' => 'عنوان پیش‌نویس',
                'sections' => [['question_bank_id' => $bank->id, 'question_count' => 2]],
            ])
            ->mountAction('sessionExams')
            ->assertMountedActionModalSee(['عنوان پیش‌نویس', 'پیش‌نمایش نسخهٔ آزمون', 'چاپ آزمون', $question->body, $choice->body, 'گزینه a'])
            ->assertMountedActionModalSeeHtml('srcdoc=')
            ->assertMountedActionModalDontSee(['پاسخ مخصوص تصحیح', $inactive->body]);

        $this->assertDatabaseCount('exams', 0);
    }

    public function test_the_paper_preview_does_not_print_missing_or_foreign_questions(): void
    {
        if (! Module::isEnabled('Exam')) {
            $this->markTestSkipped('Exam is disabled.');
        }

        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->west);
        $session = ClassSession::factory()->for($this->classroom)->create(['kind' => SessionKind::Exam]);
        $foreignBank = QuestionBank::factory()->for($this->primary)->create();
        $foreignQuestion = Question::factory()->for($this->primary)->create(['body' => 'سؤال شعبهٔ دیگر']);
        $foreignBank->questions()->attach($foreignQuestion);
        $params = ['contextType' => 'class-session', 'contextId' => $session->id];

        Livewire::test(CreateContextExam::class, $params)
            ->mountAction('sessionExams')
            ->assertMountedActionModalSee('بانک سؤال و تعداد سؤال‌های هر ردیف را وارد کنید')
            ->assertMountedActionModalDontSee('چاپ آزمون');

        Livewire::test(CreateContextExam::class, $params)
            ->fillForm(['sections' => [['question_bank_id' => $foreignBank->id, 'question_count' => 1]]])
            ->mountAction('sessionExams')
            ->assertMountedActionModalSee('یکی از بانک‌های انتخاب‌شده در دسترس نیست.')
            ->assertMountedActionModalDontSee([$foreignQuestion->body, 'چاپ آزمون']);
    }

    public function test_exam_creation_requires_a_current_branch_exam_session_and_permission_for_both_resources(): void
    {
        if (! Module::isEnabled('Exam')) {
            $this->markTestSkipped('Exam is disabled.');
        }

        $session = ClassSession::factory()->for($this->classroom)->create(['kind' => SessionKind::Exam]);
        $cancelled = ClassSession::factory()->for($this->classroom)->create(['kind' => SessionKind::Exam, 'status' => SessionStatus::Cancelled]);
        $regular = ClassSession::factory()->for($this->classroom)->create(['kind' => SessionKind::Regular]);
        $foreign = ClassSession::factory()->for(Classroom::factory()->for($this->primary))->create(['kind' => SessionKind::Exam]);
        $url = fn (ClassSession $record): string => ExamResource::getUrl('create-for-context', [
            'contextType' => 'class-session', 'contextId' => $record->id,
        ], tenant: $this->west);

        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->west);
        $this->get($url($cancelled))->assertNotFound();
        $this->get($url($foreign))->assertNotFound();
        $this->get($url($regular))->assertForbidden();
        Livewire::test(ViewExamSession::class, ['record' => $cancelled->id])
            ->assertActionHidden('sessionExam');

        $editor = User::factory()->create();
        $this->roleWithPermissions('class_without_exam_create', ['ViewAny:Classroom', 'View:Classroom', 'Update:Classroom', 'ViewAny:Exam']);
        $this->makeStaff($editor, $this->west);
        $this->grantRole($editor, $this->west, 'class_without_exam_create');
        $this->actingInPanel($editor, $this->west);
        $this->get($url($session))->assertForbidden();
        Livewire::test(ViewExamSession::class, ['record' => $session->id])
            ->assertActionHidden('sessionExam');

        $exam = Exam::factory()->for($this->west)->create();
        $exam->forceFill(['context_type' => 'class-session', 'context_id' => $session->id])->save();
        $editUrl = ExamResource::getUrl('edit', ['record' => $exam], tenant: $this->west);
        Livewire::test(ViewExamSession::class, ['record' => $session->id])
            ->assertActionHidden('sessionExam');
        $this->get($editUrl)->assertForbidden();
    }

    public function test_exam_schedule_accepts_an_employee_from_another_tenant_and_rejects_non_employees(): void
    {
        if (! Module::isEnabled('Employee')) {
            $this->markTestSkipped('Employee is disabled.');
        }

        $employee = Employee::factory()->for($this->primary)
            ->for(User::factory()->state(['name' => 'کارمند نمونه', 'national_code' => '0013542419']))
            ->create();
        $editor = User::factory()->create();
        $this->roleWithPermissions('class_editor', ['ViewAny:Classroom', 'View:Classroom', 'Update:Classroom']);
        $this->makeStaff($editor, $this->west);
        $this->grantRole($editor, $this->west, 'class_editor');
        $this->actingInPanel($editor, $this->west);
        $action = 'addExamSchedule';
        $slot = ['date' => '2026-11-06', 'start_time' => '10:00', 'end_time' => '11:00', 'mode' => ClassMode::InPerson->value];
        $label = [$employee->user_id => 'کارمند نمونه (0013542419)'];
        $this->assertSame($label, $this->planner->invigilatorOptions('کارمند نمونه'));
        $this->assertSame($label, $this->planner->invigilatorOptions(selected: [$employee->user_id]));

        Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class])
            ->callAction($action, data: ['sessions' => [[...$slot, 'teacher_ids' => [$this->teacher->id]]]])
            ->assertHasActionErrors(['sessions.0.teacher_ids.0']);
        $this->assertFalse($this->classroom->sessions()->exists());

        Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class])
            ->callAction($action, data: ['sessions' => [[...$slot, 'teacher_ids' => [$employee->user_id]]]])
            ->assertHasNoActionErrors();
        $this->assertSame([$employee->user_id], $this->classroom->sessions()->sole()->teachers->modelKeys());
    }

    public function test_an_exam_schedule_clash_keeps_the_form_open_without_saving_any_exam_sessions(): void
    {
        $this->weekly([Weekday::Saturday], '2026-10-31', '2026-11-07');
        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->west);
        $action = 'addExamSchedule';

        Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class])
            ->callAction($action, data: ['sessions' => [
                ['date' => '2026-11-06', 'start_time' => '08:30', 'end_time' => '09:30', 'mode' => ClassMode::InPerson->value],
                ['date' => '2026-11-07', 'start_time' => '08:30', 'end_time' => '09:30', 'mode' => ClassMode::Online->value],
            ]])
            ->assertNotified()
            ->assertActionMounted($action);

        $this->assertSame(['2026-10-31', '2026-11-07'], $this->sessionDays());
    }

    public function test_editing_an_upcoming_exam_updates_the_same_session_and_checks_clashes(): void
    {
        if (! Module::isEnabled('Employee')) {
            $this->markTestSkipped('Employee is disabled.');
        }

        $editor = User::factory()->superAdmin()->create();
        $invigilator = Employee::factory()->for($this->primary)->create();
        $subject = Subject::factory()->for($this->primary)->create();
        $this->actingInPanel($editor, $this->west);
        $this->planner->addExamSessions($this->classroom, [
            ['date' => '2026-11-06', 'start_time' => '10:00', 'end_time' => '11:00'],
        ]);
        $exam = $this->classroom->sessions()->where('kind', SessionKind::Exam)->sole();
        $this->planner->addSession($this->classroom, [
            'date' => '2026-11-07', 'start_time' => '10:00', 'end_time' => '11:00',
        ]);
        $action = 'editExamSchedule';
        $changes = [
            'date' => '2026-11-07', 'start_time' => '10:00', 'end_time' => '11:00',
            'subject_id' => $subject->id, 'teacher_ids' => [$invigilator->user_id],
            'location' => 'سالن امتحان', 'note' => 'نسخهٔ به‌روزشده', 'title' => 'آزمون میان‌ترم',
        ];

        $page = Livewire::test(ViewExamSession::class, ['record' => $exam->id])
            ->mountAction($action)
            ->assertSchemaStateSet(['date' => '2026-11-06', 'start_time' => '10:00', 'location' => 'کلاس ۱۰۱'], 'mountedActionSchema0')
            ->fillForm($changes)
            ->callMountedAction()
            ->assertActionMounted($action);

        $this->assertNull($exam->fresh()->note);
        $this->assertSame([], $exam->teachers()->pluck('users.id')->all());

        $page->fillForm(['date' => '2026-11-08'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('برنامهٔ امتحانی به‌روز شد.');

        $exam->refresh();
        $this->assertSame('2026-11-08 10:00', $exam->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-06 10:00', $exam->original_starts_at->format('Y-m-d H:i'));
        $this->assertSame($subject->id, $exam->subject_id);
        $this->assertSame([$invigilator->user_id], $exam->teachers()->pluck('users.id')->all());
        $this->assertSame('سالن امتحان', $exam->location);
        $this->assertSame('نسخهٔ به‌روزشده', $exam->note);
        $this->assertSame('آزمون میان‌ترم', app(SessionCalendar::class)->card($exam)['title']);
        $this->assertSame($editor->id, $exam->changed_by_id);
    }

    public function test_editing_a_past_exam_can_correct_details_without_moving_the_session(): void
    {
        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->west);
        $this->planner->addExamSessions($this->classroom, [
            ['date' => '2026-10-28', 'start_time' => '10:00', 'end_time' => '11:00'],
        ]);
        $exam = $this->classroom->sessions()->where('kind', SessionKind::Exam)->sole();

        Livewire::test(ViewExamSession::class, ['record' => $exam->id])
            ->callAction('editExamSchedule', data: [
                'date' => '2026-11-08', 'location' => 'سالن امتحان', 'note' => 'اصلاح توضیحات',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('برنامهٔ امتحانی به‌روز شد.');

        $exam->refresh();
        $this->assertSame('2026-10-28 10:00', $exam->starts_at->format('Y-m-d H:i'));
        $this->assertSame('سالن امتحان', $exam->location);
        $this->assertSame('اصلاح توضیحات', $exam->note);
    }

    public function test_exam_scheduling_rejects_an_end_time_before_the_start_time(): void
    {
        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->west);
        $action = 'addExamSchedule';

        Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class])
            ->callAction($action, data: ['sessions' => [
                ['date' => '2026-11-06', 'start_time' => '10:30', 'end_time' => '09:30', 'mode' => ClassMode::InPerson->value],
            ]])
            ->assertHasActionErrors(['sessions.0.end_time' => 'after'])
            ->assertActionMounted($action);

        $this->assertFalse($this->classroom->sessions()->exists());
    }

    public function test_exam_scheduling_is_limited_to_class_editors_in_the_current_tenant(): void
    {
        $viewer = User::factory()->create();
        $this->roleWithPermissions('class_viewer', ['ViewAny:Classroom', 'View:Classroom']);
        $this->makeStaff($viewer, $this->west);
        $this->grantRole($viewer, $this->west, 'class_viewer');
        $foreignClass = Classroom::factory()->for($this->primary)->create();

        $this->actingInPanel($viewer, $this->west);
        $exam = ClassSession::factory()->for($this->classroom)->create(['kind' => SessionKind::Exam]);
        Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class])
            ->assertActionHidden('addExamSchedule');
        Livewire::test(ViewExamSession::class, ['record' => $exam->id])
            ->assertActionHidden('editExamSchedule');

        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->west);
        $foreignExam = ClassSession::factory()->for($foreignClass)->create(['kind' => SessionKind::Exam]);
        $this->get(ClassroomResource::getUrl('sessions', ['record' => $foreignClass], tenant: $this->west))->assertNotFound();
        $this->get(ClassSessionResource::detailsUrl($foreignExam, $this->west))->assertNotFound();
    }

    public function test_the_sessions_list_shows_the_current_tenants_sessions_only(): void
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);
        $mine = ClassSession::factory()->create(['classroom_id' => $this->classroom->id]);
        $theirs = $this->primary->execute(fn () => ClassSession::factory()->create(['classroom_id' => Classroom::factory()->for($this->primary)->create()->id]));

        $this->actingInPanel($admin, $this->west);
        Livewire::test(ListClassSessions::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->get(ClassSessionResource::getUrl('index', tenant: $this->west))->assertOk();
    }

    /**
     * A weekly schedule of the class; $teacher -1 = the class's teacher.
     *
     * @param  list<Weekday>  $days
     */
    private function weekly(array $days, string $from, string $to, ?int $teacher = -1, string $fromTime = '08:00', string $toTime = '09:30'): ClassSchedule
    {
        return $this->planner->saveSchedule(null, $this->classroom, $this->data($days, $from, $to, $fromTime, $toTime, $teacher === -1 ? $this->teacher->id : $teacher));
    }

    /**
     * @param  list<Weekday>  $days
     * @return array<string, mixed>
     */
    private function data(array $days, string $from, string $to, string $fromTime = '08:00', string $toTime = '09:30', ?int $teacher = null): array
    {
        return [
            'frequency' => Frequency::Weekly, 'interval' => 1, 'weekdays' => array_map(fn (Weekday $day): int => $day->value, $days),
            'starts_on' => $from, 'ends_on' => $to, 'start_time' => $fromTime, 'end_time' => $toTime, 'teacher_ids' => $teacher === null ? [] : [$teacher],
        ];
    }

    /**
     * @return list<string>
     */
    private function sessionDays(): array
    {
        return ClassSession::query()->where('classroom_id', $this->classroom->id)->orderBy('starts_at')->get()
            ->map(fn (ClassSession $session): string => $session->starts_at->toDateString())->all();
    }

    private function teacherOf(Classroom $classroom): User
    {
        $user = $this->grantTeacher(User::factory()->create(), $this->west);
        app(ClassMembers::class)->add($classroom, $this->teacherRole(), [$user->id]);

        return $user;
    }

    private function grantTeacher(User $user, Tenant $tenant): User
    {
        $this->grantRole($user, $tenant, 'teacher');

        return $user;
    }

    private function teacherRole(): ClassRole
    {
        return ClassRole::definedRoles()->where('title', 'مدرس')->first() ?? ClassRole::factory()->for($this->primary)->teacher()->create();
    }

    private function assertClash(callable $operation, string $message): void
    {
        try {
            $operation();
        } catch (ClassException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }

        $this->fail("No error «{$message}».");
    }
}

<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Enums\Weekday;
use Modules\Class\Filament\Resources\Classrooms\Pages\ViewClassroom;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SchedulesRelationManager;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SessionsRelationManager;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ListClassSessions;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\Holiday;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\SessionPlanner;
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

    public function test_a_weekly_schedule_builds_its_future_sessions_without_holidays(): void
    {
        Holiday::factory()->for($this->primary)->create(['starts_on' => '2026-11-07', 'ends_on' => '2026-11-07']);
        Holiday::factory()->for($this->west)->create(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-09']);
        Holiday::factory()->for(Tenant::factory()->create())->create(['starts_on' => '2026-11-14', 'ends_on' => '2026-11-14']);

        // From Monday 10-26: the past Monday and Wednesday get no sessions.
        $this->weekly([Weekday::Saturday, Weekday::Monday, Weekday::Wednesday], '2026-10-26', '2026-11-16');

        $this->assertSame(
            ['2026-10-31', '2026-11-02', '2026-11-04', '2026-11-11', '2026-11-14', '2026-11-16'],
            $this->sessionDays(),
        );
        $session = ClassSession::query()->orderBy('starts_at')->first();
        $this->assertSame('08:00', $session->starts_at->format('H:i'));
        $this->assertSame('09:30', $session->ends_at->format('H:i'));
        $this->assertSame($this->teacher->id, $session->teacher_id);
        $this->assertSame('کلاس ۱۰۱', $session->location);
        $this->assertSame(SessionKind::Regular, $session->kind);
    }

    public function test_editing_a_schedule_rebuilds_only_untouched_future_sessions(): void
    {
        $schedule = $this->weekly([Weekday::Saturday, Weekday::Monday], '2026-10-31', '2026-11-16');
        [$moved, $cancelled, $substituted] = ClassSession::query()->orderBy('starts_at')->take(3)->get()->all();
        $this->planner->move($moved, '2026-10-31', '14:00', '15:00', null);
        $this->planner->cancel($cancelled, 'بیماری مدرس');
        $this->planner->setSubstitute($substituted, $this->teacherOf($this->classroom)->id);

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
        $this->assertClash(fn () => $this->primary->execute(fn () => $this->planner->saveSchedule(null, $elsewhere, $this->data([Weekday::Saturday], '2026-11-07', '2026-11-07', '09:00', '10:00', $this->teacher->id))), 'مدرس در «'.$this->classroom->title);

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
        $owner = ['ownerRecord' => $this->classroom, 'pageClass' => ViewClassroom::class];

        $this->actingInPanel($viewer, $this->west);
        Livewire::test(SchedulesRelationManager::class, $owner)->assertActionHidden(TestAction::make('addSchedule')->table());

        $this->actingInPanel($editor, $this->west);
        Livewire::test(SchedulesRelationManager::class, $owner)
            ->callAction(TestAction::make('addSchedule')->table(), data: [
                'frequency' => Frequency::Weekly->value, 'interval' => 1, 'weekdays' => [Weekday::Sunday->value, Weekday::Tuesday->value],
                'starts_on' => '2026-11-01', 'ends_on' => '2026-11-10', 'start_time' => '10:00', 'end_time' => '11:30', 'teacher_id' => $this->teacher->id,
            ])
            ->assertHasNoActionErrors();
        $this->assertSame(['2026-11-01', '2026-11-03', '2026-11-08', '2026-11-10'], $this->sessionDays());

        $first = ClassSession::query()->orderBy('starts_at')->first();
        Livewire::test(SessionsRelationManager::class, $owner)
            ->callAction(TestAction::make('cancel')->table($first), data: ['reason' => 'جلسه‌ی اولیا'])
            ->assertHasNoActionErrors()
            ->callAction(TestAction::make('addSession')->table(), data: [
                'kind' => SessionKind::MakeUp->value, 'date' => '2026-11-05', 'start_time' => '10:00', 'end_time' => '11:00',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(SessionStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(SessionKind::MakeUp, ClassSession::query()->whereDate('starts_at', '2026-11-05')->sole()->kind);
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
            'starts_on' => $from, 'ends_on' => $to, 'start_time' => $fromTime, 'end_time' => $toTime, 'teacher_id' => $teacher,
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

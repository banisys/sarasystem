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
use Modules\Class\Filament\Resources\Classrooms\Pages\ViewClassroom;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\MembersRelationManager;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SessionsRelationManager;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\AttendanceRegister;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ClassMembers;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Taking the attendance of class sessions (ADR 0024). The session is on
 * 2026-11-01 10:00–11:30.
 */
class ClassAttendanceTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private Classroom $classroom;

    private ClassSession $session;

    private User $teacher;

    private User $supervisor;

    /** @var list<User> */
    private array $students;

    private AttendanceRegister $register;

    protected function setUp(): void
    {
        parent::setUp();

        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
        $this->roleWithPermissions('member', []);

        $learner = ClassRole::factory()->for($primary)->attendee()->create();
        $teacherRole = ClassRole::factory()->for($primary)->teacher()->create();
        $supervisorRole = ClassRole::factory()->for($primary)->create(['title' => 'ناظر', 'takes_attendance' => true]);
        $observer = ClassRole::factory()->for($primary)->create(['title' => 'مشاور']);

        $this->classroom = Classroom::factory()->for($this->west)->create();
        $members = app(ClassMembers::class);
        $this->students = [$this->person(), $this->person(), $this->person()];
        $members->add($this->classroom, $learner, array_map(fn (User $user): int => $user->id, $this->students), CarbonImmutable::parse('2026-10-01'));
        $members->add($this->classroom, $teacherRole, [($this->teacher = $this->person())->id]);
        $members->add($this->classroom, $supervisorRole, [($this->supervisor = $this->person())->id]);
        $members->add($this->classroom, $observer, [$this->person()->id]);

        // The third student joins after the session.
        ClassMember::query()->where('user_id', $this->students[2]->id)->update(['joined_on' => '2026-11-10']);

        $this->session = ClassSession::factory()->create([
            'classroom_id' => $this->classroom->id, 'teacher_id' => $this->teacher->id,
            'starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-01 11:30',
        ]);
        $this->register = app(AttendanceRegister::class);
    }

    public function test_the_list_is_the_attendees_who_were_in_the_class_that_day(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->students[0]->id, $this->students[1]->id],
            $this->register->attendees($this->session)->modelKeys(),
        );
    }

    public function test_the_teacher_records_it_and_the_session_is_held(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:05'));

        $this->register->record($this->session, [
            $this->students[0]->id => ['status' => 'present'],
            $this->students[1]->id => ['status' => 'late', 'late_minutes' => 10, 'note' => ' ترافیک '],
        ], $this->teacher);

        $late = Attendance::query()->where('user_id', $this->students[1]->id)->sole();
        $this->assertSame(AttendanceStatus::Late, $late->status);
        $this->assertSame(10, $late->late_minutes);
        $this->assertSame('ترافیک', $late->note);
        $this->assertSame($this->teacher->id, $late->recorded_by_id);
        $this->assertSame($this->classroom->id, $late->classroom_id);
        $this->assertSame(SessionStatus::Held, $this->session->fresh()->status);

        // Changing one row keeps who recorded the others.
        $this->register->record($this->session, [
            $this->students[0]->id => ['status' => 'present'],
            $this->students[1]->id => ['status' => 'excused', 'late_minutes' => 10],
        ], $this->supervisor);
        $this->assertNull($late->fresh()->late_minutes);
        $this->assertSame($this->supervisor->id, $late->fresh()->recorded_by_id);
        $this->assertSame($this->teacher->id, Attendance::query()->where('user_id', $this->students[0]->id)->sole()->recorded_by_id);
    }

    public function test_who_may_take_it_and_when(): void
    {
        $observer = ClassMember::query()->whereHas('classRole', fn ($role) => $role->where('title', 'مشاور'))->sole()->user;
        $substitute = $this->person();
        $editor = $this->person();
        $this->roleWithPermissions('class_editor', ['ViewAny:Classroom', 'View:Classroom', 'Update:Classroom']);
        $this->grantRole($editor, $this->west, 'class_editor');

        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:30'));
        $this->assertStringContainsString('پیش از شروع', $this->register->refusal($this->teacher, $this->session));

        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:50'));
        $this->assertTrue($this->register->canTake($this->teacher, $this->session));
        $this->assertTrue($this->register->canTake($this->supervisor, $this->session));
        $this->assertFalse($this->register->canTake($observer, $this->session));
        $this->assertFalse($this->register->canTake($this->students[0], $this->session));
        $this->assertFalse($this->register->canTake($substitute, $this->session));
        $this->session->update(['substitute_teacher_id' => $substitute->id]);
        $this->assertTrue($this->register->canTake($substitute, $this->session));

        // Closed for teachers after three days, not for whoever may edit the class.
        $this->travelTo(CarbonImmutable::parse('2026-11-05 00:01'));
        $this->assertStringContainsString('مهلت', $this->register->refusal($this->teacher, $this->session));
        $this->assertTrue($this->register->canTake($editor, $this->session));

        $this->session->update(['status' => SessionStatus::Cancelled]);
        $this->assertFalse($this->register->canTake($editor, $this->session));
    }

    public function test_only_the_sessions_attendees_are_recorded(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:05'));

        $this->expectException(ClassException::class);
        $this->register->record($this->session, [$this->students[2]->id => ['status' => 'present']], $this->teacher);
    }

    public function test_the_tabs_take_attendance_and_count_absences(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:05'));
        $this->makeStaff($this->teacher, $this->west);
        $this->roleWithPermissions('class_viewer', ['ViewAny:Classroom', 'View:Classroom']);
        $this->grantRole($this->teacher, $this->west, 'class_viewer');
        $owner = ['ownerRecord' => $this->classroom, 'pageClass' => ViewClassroom::class];

        $this->actingInPanel($this->teacher, $this->west);
        $tab = Livewire::test(SessionsRelationManager::class, $owner)
            ->assertActionVisible(TestAction::make('attendance')->table($this->session))
            ->assertActionHidden(TestAction::make('cancel')->table($this->session))
            ->mountAction(TestAction::make('attendance')->table($this->session));

        // Filled with the attendees, everyone «حاضر»; one is marked absent.
        $rows = $tab->instance()->mountedActions[0]['data']['rows'];
        $this->assertSame(['present', 'present'], array_values(array_column($rows, 'status')));
        $tab->setActionData(['rows' => array_map(fn (array $row): array => [...$row, 'status' => $row['user_id'] === $this->students[1]->id ? 'absent' : 'present'], $rows)])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(AttendanceStatus::Absent, Attendance::query()->where('user_id', $this->students[1]->id)->sole()->status);

        $absent = ClassMember::query()->where('user_id', $this->students[1]->id)->withAttendanceCounts()->sole();
        $this->assertSame(1, $absent->absences_count);
        Livewire::test(MembersRelationManager::class, $owner)
            ->assertTableColumnStateSet('attendance', 'غیبت 1 · تأخیر 0', $absent);
    }

    private function person(): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->west, 'member');

        return $user;
    }
}

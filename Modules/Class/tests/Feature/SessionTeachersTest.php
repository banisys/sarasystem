<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;
use Modules\Class\Filament\Resources\Classrooms\Pages\ClassSchedules;
use Modules\Class\Filament\Resources\Classrooms\Pages\ClassSessions;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SchedulesRelationManager;
use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SessionsRelationManager;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\AttendanceRegister;
use Modules\Class\Services\ClassException;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\SessionPlanner;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Several teachers per schedule and session, a substitute standing in for
 * one of them (ADR 0068). Today is Thursday 2026-10-29; 2026-10-31 is a
 * Saturday.
 */
class SessionTeachersTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private Classroom $classroom;

    private ClassRole $teacherRole;

    private User $maryam;

    private User $reza;

    private SessionPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));

        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();

        $this->roleWithPermissions('teacher', []);
        $this->teacherRole = ClassRole::factory()->for($primary)->teacher()->create();
        $this->classroom = Classroom::factory()->for($this->west)->create();
        $this->maryam = $this->teacherOf($this->classroom, 'مریم احمدی');
        $this->reza = $this->teacherOf($this->classroom, 'رضا کریمی');
        $this->planner = app(SessionPlanner::class);
    }

    public function test_a_schedule_gives_all_its_teachers_to_its_sessions(): void
    {
        $schedule = $this->schedule([$this->maryam->id, $this->reza->id]);

        $this->assertEqualsCanonicalizing([$this->maryam->id, $this->reza->id], $schedule->teachers->modelKeys());
        $sessions = ClassSession::query()->with('teachers')->get();
        $this->assertCount(2, $sessions);
        foreach ($sessions as $session) {
            $this->assertEqualsCanonicalizing([$this->maryam->id, $this->reza->id], $session->actualTeacherIds());
        }

        // Both teach it: both calendars, both take attendance.
        $this->assertSame(2, ClassSession::query()->taughtBy($this->maryam)->count());
        $this->assertSame(2, ClassSession::query()->taughtBy($this->reza)->count());
        $this->travelTo($sessions->first()->starts_at->copy()->addMinutes(5));
        $register = app(AttendanceRegister::class);
        $this->assertTrue($register->canTake($this->maryam, $sessions->first()));
        $this->assertTrue($register->canTake($this->reza, $sessions->first()));

        // Editing the schedule's teachers changes its untouched sessions.
        $this->travelBack();
        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));
        $this->planner->saveSchedule($schedule, $this->classroom, $this->data([$this->reza->id]));
        $this->assertSame([[$this->reza->id], [$this->reza->id]], ClassSession::query()->with('teachers')->orderBy('starts_at')->get()->map->actualTeacherIds()->all());
    }

    public function test_each_teacher_must_be_free(): void
    {
        $other = Classroom::factory()->for($this->west)->create(['location' => 'جای دیگر']);
        app(ClassMembers::class)->add($other, $this->teacherRole, [$this->reza->id]);
        $this->planner->saveSchedule(null, $other, $this->data([$this->reza->id]));

        try {
            $this->schedule([$this->maryam->id, $this->reza->id]);
            $this->fail('A clash with the second teacher was not refused.');
        } catch (ClassException $exception) {
            $this->assertStringContainsString('مدرس «رضا کریمی» در «'.$other->title, $exception->getMessage());
        }

        $this->schedule([$this->maryam->id]);
        $this->assertSame(2, $this->classroom->sessions()->count());
    }

    public function test_a_substitute_stands_in_for_one_teacher_only(): void
    {
        $schedule = $this->schedule([$this->maryam->id, $this->reza->id]);
        $session = ClassSession::query()->orderBy('starts_at')->first();
        $sara = $this->grantTeacher(User::factory()->create(['name' => 'سارا رضایی']), $this->west);

        $this->planner->setSubstitute($session, $this->maryam->id, $sara->id);

        $session = $session->fresh(['teachers', 'substitutes']);
        $this->assertEqualsCanonicalizing([$sara->id, $this->reza->id], $session->actualTeacherIds());
        $this->assertSame('رضا کریمی، سارا رضایی (جایگزین مریم احمدی)', $session->teacherNames());
        $this->assertFalse(ClassSession::query()->whereKey($session->id)->taughtBy($this->maryam)->exists());
        $this->assertTrue(ClassSession::query()->whereKey($session->id)->taughtBy($this->reza)->exists());
        $this->assertTrue(ClassSession::query()->whereKey($session->id)->taughtBy($sara)->exists());
        $this->assertFalse($session->isUntouched());

        // Rebuilding the schedule leaves the substituted session alone.
        $this->planner->saveSchedule($schedule, $this->classroom, $this->data([$this->reza->id]));
        $this->assertEqualsCanonicalizing([$sara->id, $this->reza->id], $session->fresh('teachers')->actualTeacherIds());

        // Taking it back.
        $this->planner->setSubstitute($session, $this->maryam->id, null);
        $this->assertEqualsCanonicalizing([$this->maryam->id, $this->reza->id], $session->fresh('teachers')->actualTeacherIds());
    }

    public function test_a_session_gets_its_own_teachers(): void
    {
        $this->schedule([$this->maryam->id]);
        $session = ClassSession::query()->orderBy('starts_at')->first();

        $this->planner->setTeachers($session, [['teacher_id' => $this->maryam->id], ['teacher_id' => $this->reza->id]]);
        $session = $session->fresh('teachers');
        $this->assertEqualsCanonicalizing([$this->maryam->id, $this->reza->id], $session->actualTeacherIds());
        $this->assertTrue($session->is_modified);

        $outsider = $this->grantTeacher(User::factory()->create(), $this->west);
        $this->expectExceptionMessage('مدرس باید عضو کلاس');
        $this->planner->setTeachers($session, [['teacher_id' => $outsider->id]]);
    }

    public function test_a_person_appears_once_among_the_session_teachers(): void
    {
        $this->schedule([$this->maryam->id, $this->reza->id]);
        $session = ClassSession::query()->first();

        $this->expectExceptionMessage('فقط یک بار');
        $this->planner->setTeachers($session, [
            ['teacher_id' => $this->maryam->id, 'substitute_id' => $this->reza->id],
            ['teacher_id' => $this->reza->id],
        ]);
    }

    public function test_the_forms_take_several_teachers(): void
    {
        $editor = User::factory()->create();
        $this->roleWithPermissions('class_editor', ['ViewAny:Classroom', 'View:Classroom', 'Update:Classroom']);
        $this->makeStaff($editor, $this->west);
        $this->grantRole($editor, $this->west, 'class_editor');
        $this->actingInPanel($editor, $this->west);

        Livewire::test(SchedulesRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSchedules::class])
            ->callAction(TestAction::make('addSchedule')->table(), data: [
                'frequency' => Frequency::Weekly->value, 'interval' => 1, 'weekdays' => [Weekday::Saturday->value],
                'starts_on' => '2026-10-31', 'ends_on' => '2026-11-07', 'start_time' => '08:00', 'end_time' => '09:30',
                'teacher_ids' => [$this->maryam->id, $this->reza->id],
            ])
            ->assertHasNoActionErrors();
        $this->assertEqualsCanonicalizing([$this->maryam->id, $this->reza->id], ClassSchedule::query()->sole()->teachers->modelKeys());

        $session = ClassSession::query()->orderBy('starts_at')->first();
        $sara = $this->grantTeacher(User::factory()->create(), $this->west);
        Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->classroom, 'pageClass' => ClassSessions::class])
            ->callAction(TestAction::make('substitute')->table($session), data: [
                'rows' => [
                    ['teacher_id' => $this->maryam->id, 'substitute_id' => $sara->id],
                    ['teacher_id' => $this->reza->id, 'substitute_id' => null],
                ],
            ])
            ->assertHasNoActionErrors();
        $this->assertEqualsCanonicalizing([$sara->id, $this->reza->id], $session->fresh('teachers')->actualTeacherIds());
    }

    /**
     * @param  list<int>  $teacherIds
     */
    private function schedule(array $teacherIds): ClassSchedule
    {
        return $this->planner->saveSchedule(null, $this->classroom, $this->data($teacherIds));
    }

    /**
     * Saturdays 2026-10-31 and 2026-11-07, 08:00–09:30.
     *
     * @param  list<int>  $teacherIds
     * @return array<string, mixed>
     */
    private function data(array $teacherIds): array
    {
        return [
            'frequency' => Frequency::Weekly, 'interval' => 1, 'weekdays' => [Weekday::Saturday->value],
            'starts_on' => '2026-10-31', 'ends_on' => '2026-11-07', 'start_time' => '08:00', 'end_time' => '09:30', 'teacher_ids' => $teacherIds,
        ];
    }

    private function teacherOf(Classroom $classroom, string $name): User
    {
        $user = $this->grantTeacher(User::factory()->create(['name' => $name]), $this->west);
        app(ClassMembers::class)->add($classroom, $this->teacherRole, [$user->id]);

        return $user;
    }

    private function grantTeacher(User $user, Tenant $tenant): User
    {
        $this->grantRole($user, $tenant, 'teacher');

        return $user;
    }
}

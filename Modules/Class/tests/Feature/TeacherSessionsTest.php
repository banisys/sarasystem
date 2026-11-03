<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Filament\Resources\ClassSessions\Pages\TeacherSessions;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionCalendar;
use Modules\Employee\Filament\Resources\Employees\Pages\ListEmployees;
use Modules\Employee\Models\Employee;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * A teacher's sessions on a calendar, from the employees list (ADR 0048).
 * Now is Thursday 2026-10-29 (1405/08/07) 12:00.
 */
class TeacherSessionsTest extends TestCase
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
        Tenant::factory()->create()->execute(fn () => ClassSession::factory()->create([
            'classroom_id' => Classroom::factory()->for(Tenant::current())->create()->id,
            'teacher_id' => $this->teacher->id,
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
            ->assertActionHidden(TestAction::make('sessionDetails')->arguments(['session' => $notMine->id]))
            ->mountAction(TestAction::make('sessionDetails')->arguments(['session' => $mine->id]))
            ->assertMountedActionModalSee(['برنامه‌ریزی‌شده', 'زبان مقدماتی الف']);
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

    private function classSession(string $startsAt, SessionStatus $status = SessionStatus::Scheduled, ?User $teacher = null, ?User $substitute = null): ClassSession
    {
        $start = CarbonImmutable::parse($startsAt);

        return ClassSession::factory()->create([
            'classroom_id' => $this->classroom->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(90),
            'status' => $status,
            'teacher_id' => ($teacher ?? $this->teacher)->id,
            'substitute_teacher_id' => $substitute?->id,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin(Tenant::primary());

        return $admin;
    }
}

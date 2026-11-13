<?php

namespace Modules\Class\Tests\Feature;

use App\Auth\Impersonation;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ListClassSessions;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ViewClassSession;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSchedule;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionPlanner;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * One session's page, opened in a new tab from a calendar or a list (ADR 0050).
 */
class SessionPageTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));

        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
        $this->classroom = Classroom::factory()->for($this->west)->create(['title' => 'زبان مقدماتی الف']);
    }

    public function test_the_page_shows_the_details_and_links_back_to_the_class(): void
    {
        $session = $this->classSession();

        $this->actingInPanel($this->admin(), $this->west);
        $this->get(ClassSessionResource::getUrl('view', ['record' => $session], tenant: $this->west))
            ->assertOk()
            ->assertSee(['زبان مقدماتی الف', 'برنامه‌ریزی‌شده', 'نحوه‌ی برگزاری', 'سایر', 'حضور و غیاب'])
            ->assertSee(ClassroomResource::getUrl('sessions', ['record' => $this->classroom], tenant: $this->west));
    }

    public function test_the_page_keeps_the_time_in_the_tab_title_and_counts_attendance_by_status(): void
    {
        $session = $this->classSession();
        $session->update(['starts_at' => '2026-10-29 09:00', 'ends_at' => '2026-10-29 10:30']);
        $student = User::factory()->create();
        $session->attendances()->create(['classroom_id' => $this->classroom->id, 'user_id' => $student->id, 'status' => AttendanceStatus::Absent, 'recorded_at' => now()]);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->assertSee(['مشخصات جلسه', 'مطالب و تکلیف', 'غایب', '1 نفر'])
            ->assertSee("\u{2066}09:00–10:30\u{2069}")
            ->assertDontSee('تکلیف دارد');
    }

    public function test_another_tenants_session_is_not_found(): void
    {
        $theirs = Tenant::factory()->create()->execute(fn (): ClassSession => ClassSession::factory()->create([
            'classroom_id' => Classroom::factory()->for(Tenant::current())->create()->id,
        ]));

        $this->actingInPanel($this->admin(), $this->west);
        $this->get(ClassSessionResource::getUrl('view', ['record' => $theirs->id], tenant: $this->west))->assertNotFound();
    }

    public function test_an_exam_has_its_own_details_page_and_the_old_link_redirects(): void
    {
        $exam = $this->classSession(['kind' => SessionKind::Exam, 'note' => 'همراه داشتن کارت ورود به جلسه']);
        $invigilator = User::factory()->create(['name' => 'آزاده شریفی']);
        $exam->teachers()->attach($invigilator);
        $this->actingInPanel($this->admin(), $this->west);

        $url = ClassSessionResource::getUrl('view-exam', ['record' => $exam], tenant: $this->west);
        $this->get($url)->assertOk()
            ->assertSee(['جلسهٔ امتحانی', 'مراقبان', $invigilator->name, 'همراه داشتن کارت ورود به جلسه', 'حضور و غیاب'])
            ->assertDontSee('مطالب و تکلیف');
        $this->get(ClassSessionResource::getUrl('view', ['record' => $exam], tenant: $this->west))->assertRedirect($url);
    }

    public function test_the_exam_page_rejects_regular_sessions_and_exams_from_other_tenants(): void
    {
        $regular = $this->classSession(['kind' => SessionKind::Regular]);
        $theirs = Tenant::factory()->create()->execute(fn (): ClassSession => ClassSession::factory()->create([
            'classroom_id' => Classroom::factory()->for(Tenant::current())->create()->id,
            'kind' => SessionKind::Exam,
        ]));

        $this->actingInPanel($this->admin(), $this->west);
        foreach ([$regular, $theirs] as $record) {
            $this->get(ClassSessionResource::getUrl('view-exam', ['record' => $record], tenant: $this->west))->assertNotFound();
        }
    }

    public function test_the_super_admin_can_impersonate_a_session_teacher_and_return_to_the_session(): void
    {
        $teacher = User::factory()->create(['name' => 'مدرس جلسه']);
        $session = $this->classSession();
        $session->teachers()->attach($teacher);
        $this->actingInPanel($admin = $this->admin(), $this->west);
        $url = ClassSessionResource::getUrl('view', ['record' => $session], tenant: $this->west);

        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->assertDontSee('افراد جلسه')
            ->callAction(TestAction::make('impersonate')->schemaComponent('teacher_'.$teacher->id, 'infolist'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($teacher);
        $this->assertSame($admin->id, session(Impersonation::SESSION_KEY));
        $this->post(route('impersonation.leave'))->assertRedirect($url);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_session_viewers_cannot_impersonate_its_people(): void
    {
        $teacher = User::factory()->create();
        $session = $this->classSession();
        $session->teachers()->attach($teacher);
        $this->roleWithPermissions('sessions_viewer', ['ViewAny:ClassSession']);
        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $this->west);
        $this->grantRole($viewer, $this->west, 'sessions_viewer');
        $this->actingInPanel($viewer, $this->west);

        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->assertDontSee('افراد جلسه')
            ->assertActionHidden(TestAction::make('impersonate')->schemaComponent('teacher_'.$teacher->id, 'infolist'));

        $this->assertAuthenticatedAs($viewer);
        $this->assertFalse(Impersonation::isActive());
    }

    public function test_teacher_and_attendance_actions_are_scoped_to_the_session(): void
    {
        $session = $this->classSession();
        $teacher = User::factory()->create();
        $substitute = User::factory()->create();
        $learner = User::factory()->create();
        $session->teachers()->attach($teacher, ['substitute_id' => $substitute->id]);
        ClassMember::factory()->for($this->classroom)->create([
            'user_id' => $learner->id,
            'class_role_id' => ClassRole::factory()->attendee()->create()->id,
        ]);
        $otherTenant = Tenant::factory()->create();
        $outsider = User::factory()->create(['name' => 'فرد شعبه دیگر']);
        $this->grantRole($outsider, $otherTenant, 'student');
        $this->actingInPanel($admin = $this->admin(), $this->west);
        $session->teachers()->attach($admin);

        $page = Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->assertSee([$teacher->name, $substitute->name])
            ->assertDontSee($outsider->name)
            ->assertActionVisible(TestAction::make('impersonate')->schemaComponent('teacher_'.$teacher->id, 'infolist'))
            ->assertActionVisible(TestAction::make('impersonate')->schemaComponent('substitute_'.$teacher->id, 'infolist'))
            ->assertActionHidden(TestAction::make('impersonate')->schemaComponent('teacher_'.$admin->id, 'infolist'))
            ->mountAction('attendance');

        $rows = $page->instance()->mountedActions[0]['data']['rows'];
        $this->assertSame([$learner->id], array_column($rows, 'user_id'));
        $key = array_key_first($rows);
        $action = TestAction::make('impersonate')->schemaComponent("rows.{$key}.name");

        $page->assertMountedActionModalSeeHtml('aria-label="ورود با این کاربر"')
            ->assertActionVisible($action)
            ->set("mountedActions.0.data.rows.{$key}.user_id", $outsider->id)
            ->assertActionHidden($action)
            ->set("mountedActions.0.data.rows.{$key}.user_id", $learner->id)
            ->mountAction($action)
            ->assertMountedActionModalSee('ورود با حساب '.$learner->name)
            ->callMountedAction()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($learner);
        $this->assertDatabaseMissing('class_attendances', ['session_id' => $session->id]);
        $this->post(route('impersonation.leave'))
            ->assertRedirect(ClassSessionResource::getUrl('view', ['record' => $session], tenant: $this->west));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_who_may_open_it(): void
    {
        $session = $this->classSession();
        $url = ClassSessionResource::getUrl('view', ['record' => $session], tenant: $this->west);

        // A class's tab needs only the right to see the class.
        $this->roleWithPermissions('class_viewer', ['View:Classroom']);
        $classViewer = User::factory()->create();
        $this->makeStaff($classViewer, $this->west);
        $this->grantRole($classViewer, $this->west, 'class_viewer');
        $this->actingInPanel($classViewer, $this->west);
        $this->get($url)->assertOk();

        // A person's calendar and the tenant's list need the sessions permission.
        $this->roleWithPermissions('sessions_viewer', ['ViewAny:ClassSession']);
        $sessionsViewer = User::factory()->create();
        $this->makeStaff($sessionsViewer, $this->west);
        $this->grantRole($sessionsViewer, $this->west, 'sessions_viewer');
        $this->actingInPanel($sessionsViewer, $this->west);
        $this->get($url)->assertOk();

        $stranger = User::factory()->create();
        $this->makeStaff($stranger, $this->west);
        $this->actingInPanel($stranger, $this->west);
        $this->get($url)->assertForbidden();
    }

    public function test_losing_the_right_closes_an_open_page(): void
    {
        $session = $this->classSession();
        $this->roleWithPermissions('class_viewer', ['View:Classroom']);
        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $this->west);
        $this->grantRole($viewer, $this->west, 'class_viewer');
        $this->actingInPanel($viewer, $this->west);

        $page = Livewire::test(ViewClassSession::class, ['record' => $session->id])->assertOk();

        $viewer->unsetRelation('roles')->removeRole('class_viewer');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $page->call('$refresh')->assertForbidden();
    }

    public function test_its_buttons_act_on_the_session(): void
    {
        $session = $this->classSession();

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->callAction(TestAction::make('cancelSession'), data: ['reason' => 'اردو'])
            ->assertHasNoActionErrors()
            ->assertSee('دلیل لغو: اردو')
            ->assertActionVisible(TestAction::make('restore'))
            ->assertActionHidden(TestAction::make('cancelSession'));

        $this->assertSame(SessionStatus::Cancelled, $session->fresh()->status);
    }

    /**
     * ADR 0072: each part of the page names who changed it last, and when;
     * nothing older is kept.
     */
    public function test_each_part_names_who_changed_it_last(): void
    {
        $session = $this->classSession(['starts_at' => '2026-10-30 10:00', 'ends_at' => '2026-10-30 11:30']);
        $student = User::factory()->create();
        $first = User::factory()->create(['name' => 'سارا رضایی']);
        $second = User::factory()->create(['name' => 'علی محمدی']);

        $this->actingInPanel($admin = $this->admin(), $this->west);
        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->assertDontSee('آخرین تغییر');

        // Cancelled by one, restored by another: only the last shows.
        $this->actingAs($first);
        app(SessionPlanner::class)->cancel($session, 'اردو');
        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:30'));
        $this->actingAs($second);
        app(SessionPlanner::class)->restore($session->fresh());
        $this->assertSame($second->id, $session->fresh()->changed_by_id);

        $session->attendances()->create(['classroom_id' => $this->classroom->id, 'user_id' => $student->id, 'status' => AttendanceStatus::Present, 'recorded_by_id' => $first->id, 'recorded_at' => now()->subHour()]);
        $session->update(['content_recorded_by_id' => $first->id, 'content_recorded_at' => now(), 'summary' => 'فصل ۱']);

        $this->actingInPanel($admin, $this->west);
        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->assertSee('آخرین تغییر زمان، وضعیت یا مدرس‌ها: علی محمدی · 1405/08/07 ساعت 12:30')
            ->assertSee('آخرین ثبت مطالب: سارا رضایی · 1405/08/07 ساعت 12:30')
            ->assertSee('آخرین تغییر: سارا رضایی · 1405/08/07 ساعت 11:30')
            ->assertDontSee('آخرین نمره‌دهی');
    }

    public function test_deleting_the_session_goes_back_to_its_class(): void
    {
        $session = $this->classSession(['schedule_id' => null, 'kind' => SessionKind::Extra, 'starts_at' => '2026-10-30 10:00', 'ends_at' => '2026-10-30 11:00']);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->callAction(TestAction::make('deleteSession'))
            ->assertRedirect(ClassroomResource::getUrl('sessions', ['record' => $this->classroom], tenant: $this->west));

        $this->assertNull(ClassSession::query()->find($session->id));
    }

    public function test_a_session_of_a_schedule_can_be_deleted_until_it_is_held(): void
    {
        $schedule = ClassSchedule::factory()->for($this->west)->for($this->classroom)->create();
        $upcoming = $this->classSession(['schedule_id' => $schedule->id, 'starts_at' => '2026-10-30 10:00', 'ends_at' => '2026-10-30 11:00']);
        $held = $this->classSession(['schedule_id' => $schedule->id, 'starts_at' => '2026-10-31 10:00', 'ends_at' => '2026-10-31 11:00', 'status' => SessionStatus::Held]);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ViewClassSession::class, ['record' => $held->id])
            ->assertActionHidden(TestAction::make('deleteSession'));
        Livewire::test(ViewClassSession::class, ['record' => $upcoming->id])
            ->callAction(TestAction::make('deleteSession'));

        $this->assertNull($upcoming->fresh());
        $this->assertSame(['2026-10-30'], $schedule->fresh()->skipped_dates);
    }

    public function test_a_past_unrecorded_session_can_be_deleted_but_a_running_one_cannot(): void
    {
        $past = $this->classSession(['starts_at' => '2026-10-28 10:00', 'ends_at' => '2026-10-28 11:00']);
        $running = $this->classSession(['starts_at' => '2026-10-29 11:30', 'ends_at' => '2026-10-29 12:30']);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ViewClassSession::class, ['record' => $running->id])
            ->assertActionHidden(TestAction::make('deleteSession'));
        Livewire::test(ViewClassSession::class, ['record' => $past->id])
            ->callAction(TestAction::make('deleteSession'));

        $this->assertNull($past->fresh());
    }

    public function test_the_list_opens_it_in_a_new_tab(): void
    {
        $session = $this->classSession(['starts_at' => '2026-10-30 10:00', 'ends_at' => '2026-10-30 11:00']);
        $url = ClassSessionResource::getUrl('view', ['record' => $session], tenant: $this->west);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ListClassSessions::class)
            ->assertActionHasUrl(TestAction::make('sessionDetails')->table($session), $url)
            ->assertActionShouldOpenUrlInNewTab(TestAction::make('sessionDetails')->table($session));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function classSession(array $attributes = []): ClassSession
    {
        return ClassSession::factory()->create([
            'classroom_id' => $this->classroom->id,
            'starts_at' => '2026-10-29 10:00',
            'ends_at' => '2026-10-29 11:30',
            'status' => SessionStatus::Scheduled,
            ...$attributes,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin(Tenant::primary());

        return $admin;
    }
}

<?php

namespace Modules\Class\Tests\Feature;

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
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
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

    public function test_deleting_the_session_goes_back_to_its_class(): void
    {
        $session = $this->classSession(['schedule_id' => null, 'kind' => SessionKind::Extra, 'starts_at' => '2026-10-30 10:00', 'ends_at' => '2026-10-30 11:00']);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->callAction(TestAction::make('deleteSession'))
            ->assertRedirect(ClassroomResource::getUrl('sessions', ['record' => $this->classroom], tenant: $this->west));

        $this->assertNull(ClassSession::query()->find($session->id));
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

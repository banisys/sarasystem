<?php

namespace Modules\Class\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ViewClassSession;
use Modules\Class\Filament\Resources\ClassSessions\Pages\ViewExamSession;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionScene;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The 3D view of a session in its details modal (ADR 0049).
 */
class SessionSceneTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private Classroom $classroom;

    private ClassRole $attendeeRole;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Module::find('Campus')?->isEnabled()) {
            $this->markTestSkipped('The 3D views are the Campus module\'s.');
        }

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));

        $primary = Tenant::factory()->create();
        $primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
        $this->classroom = Classroom::factory()->for($this->west)->create(['mode' => ClassMode::InPerson]);
        $this->attendeeRole = ClassRole::factory()->create(['is_attendee' => true]);
    }

    public function test_each_attendee_has_a_seat_with_their_status(): void
    {
        $session = $this->classSession();
        $present = $this->attendee('الف حاضر');
        $late = $this->attendee('ب دیرآمده');
        $absent = $this->attendee('ت غایب');
        $this->attendee('ث ثبت‌نشده');
        $this->record($session, $present, AttendanceStatus::Present);
        $this->record($session, $late, AttendanceStatus::Late, lateMinutes: 10);
        $this->record($session, $absent, AttendanceStatus::Absent);

        $scene = app(SessionScene::class)->for($session->fresh(['classroom', 'teachers']));

        $this->assertSame([
            ['name' => 'الف حاضر', 'status' => 'present', 'label' => 'حاضر'],
            ['name' => 'ب دیرآمده', 'status' => 'late', 'label' => 'تأخیر 10 دقیقه'],
            ['name' => 'ت غایب', 'status' => 'absent', 'label' => 'غایب'],
            ['name' => 'ث ثبت‌نشده', 'status' => 'unrecorded', 'label' => 'ثبت‌نشده'],
        ], $scene['seats']);
        $this->assertFalse($scene['virtual']);
        $this->assertSame(['present', 'late', 'absent', 'unrecorded'], array_keys($scene['statuses']));
        $this->assertSame(1, $scene['statuses']['absent']['count']);
        $this->assertFalse($scene['statuses']['absent']['seated']);
        $this->assertTrue($scene['statuses']['absent']['marker']);
    }

    public function test_a_substitute_teaches_and_an_online_class_is_virtual(): void
    {
        $online = Classroom::factory()->for($this->west)->create(['mode' => ClassMode::Online]);
        $teacher = User::factory()->create();
        $substitute = User::factory()->create(['name' => 'مدرس جایگزین']);
        $colleague = User::factory()->create(['name' => 'مدرس دوم']);
        $session = ClassSession::factory()->taughtBy($teacher, $substitute)->taughtBy($colleague)->create(['classroom_id' => $online->id]);

        $scene = app(SessionScene::class)->for($session);

        $this->assertTrue($scene['virtual']);
        $this->assertSame(['مدرس جایگزین', 'مدرس دوم'], array_column($scene['teachers'], 'name'));
        $this->assertSame(['مدرس', 'مدرس'], array_column($scene['teachers'], 'role'));
        $this->assertSame([], $scene['seats']);
    }

    public function test_the_sessions_page_offers_the_view_with_this_session_only(): void
    {
        $session = $this->classSession();
        $this->attendee('فراگیر همین کلاس');

        $otherClass = Classroom::factory()->for($this->west)->create();
        ClassMember::factory()->create(['classroom_id' => $otherClass->id, 'class_role_id' => $this->attendeeRole->id, 'user_id' => User::factory()->create(['name' => 'فراگیر کلاس دیگر'])->id]);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ViewClassSession::class, ['record' => $session->id])
            ->assertSee(['نمای سه‌بعدی', 'فراگیر همین کلاس', 'از بالا', 'چرخش خودکار', 'نام همه', 'دیوارها'])
            ->assertDontSee('فراگیر کلاس دیگر');
    }

    public function test_another_tenants_session_opens_no_view(): void
    {
        $theirs = Tenant::factory()->create()->execute(function (): ClassSession {
            $classroom = Classroom::factory()->for(Tenant::current())->create();

            return ClassSession::factory()->create(['classroom_id' => $classroom->id]);
        });

        $this->actingInPanel($this->admin(), $this->west);
        $this->get(ClassSessionResource::getUrl('view', ['record' => $theirs->id], tenant: $this->west))->assertNotFound();
    }

    public function test_the_exam_room_shows_attendees_and_assigned_invigilators_only(): void
    {
        $exam = $this->classSession();
        $exam->update(['kind' => SessionKind::Exam]);
        $invigilator = User::factory()->create(['name' => 'مراقب شعبهٔ دیگر']);
        $exam->teachers()->attach($invigilator);
        $supporter = $this->member('پشتیبان کلاس', ClassRole::factory()->for(Tenant::primary())->create(['title' => 'پشتیبان', 'is_attendee' => false]));
        $student = $this->attendee('فراگیر امتحان');
        $this->record($exam, $student, AttendanceStatus::Absent);

        $scene = app(SessionScene::class)->for($exam);
        $this->assertSame([[$invigilator->name, 'مراقب']], $this->people($scene['teachers']));
        $this->assertSame([], $scene['staff']);
        $this->assertSame([['name' => $student->name, 'status' => 'absent', 'label' => 'غایب']], $scene['seats']);
        $this->assertTrue($scene['statuses']['absent']['marker']);

        $this->actingInPanel($this->admin(), $this->west);
        Livewire::test(ViewExamSession::class, ['record' => $exam->id])
            ->assertSee(['نمای سه‌بعدی', 'نمای جلسهٔ امتحانی', $invigilator->name.' · مراقب', $student->name.' · غایب'])
            ->assertDontSee($supporter->name.' · پشتیبان');
    }

    public function test_the_view_is_only_for_who_may_see_the_class_or_take_its_attendance(): void
    {
        $session = $this->classSession();
        $scene = app(SessionScene::class);

        $this->assertTrue($scene->canSee($this->admin(), $session));

        $this->roleWithPermissions('sessions_viewer', ['ViewAny:ClassSession', 'View:ClassSession']);
        $sessionsOnly = User::factory()->create();
        $this->grantRole($sessionsOnly, $this->west, 'sessions_viewer');
        $this->assertFalse($scene->canSee($sessionsOnly, $session));

        $this->roleWithPermissions('class_viewer', ['View:Classroom']);
        $classViewer = User::factory()->create();
        $this->grantRole($classViewer, $this->west, 'class_viewer');
        $this->assertTrue($scene->canSee($classViewer, $session));

        $teacher = User::factory()->create();
        $session->teachers()->attach($teacher->id);
        $this->assertTrue($scene->canSee($teacher, $session->fresh()));

        // Fellow learners never see each other's attendance.
        $learner = $this->attendee('همکلاسی');
        $this->assertFalse($scene->canSee($learner, $session->fresh()));
    }

    public function test_a_cancelled_session_has_no_view(): void
    {
        $session = $this->classSession(SessionStatus::Cancelled);
        $this->attendee('فراگیر');

        $this->assertFalse(app(SessionScene::class)->canSee($this->admin(), $session));
    }

    public function test_the_class_staff_stand_in_the_room_in_their_roles_colours(): void
    {
        $session = $this->classSession();
        $primary = Tenant::primary();
        $teacherRole = ClassRole::factory()->for($primary)->create(['title' => 'مدرس', 'sort' => 1]);
        $supervisorRole = ClassRole::factory()->for($primary)->create(['title' => 'ناظر', 'sort' => 2]);
        $supporterRole = ClassRole::factory()->for($primary)->create(['title' => 'پشتیبان', 'sort' => 3]);
        $teacher = $this->member('مدرس جلسه', $teacherRole);
        $otherTeacher = $this->member('مدرس درس دیگر', $teacherRole);
        $this->member('ناظر کلاس', $supervisorRole);
        $this->member('پشتیبان یک', $supporterRole);
        $this->member('پشتیبان دو', $supporterRole);
        $this->member('پشتیبان رفته', $supporterRole, leftOn: '2026-10-01');
        $this->attendee('فراگیر');
        $session->teachers()->attach($teacher->id);
        ClassSession::factory()->taughtBy($otherTeacher)->create(['classroom_id' => $this->classroom->id]);

        $scene = app(SessionScene::class)->for($session->fresh());

        $this->assertSame([['مدرس جلسه', 'مدرس']], $this->people($scene['teachers']));
        $this->assertSame([['ناظر کلاس', 'ناظر'], ['پشتیبان دو', 'پشتیبان'], ['پشتیبان یک', 'پشتیبان']], $this->people($scene['staff']));
        $this->assertSame(['مدرس', 'ناظر', 'پشتیبان'], array_column($scene['roles'], 'label'));
        $this->assertSame([1, 1, 2], array_column($scene['roles'], 'count'));
        $this->assertCount(3, array_unique(array_column($scene['roles'], 'color')));
        $this->assertSame(SessionScene::TEACHER_COLOR, $scene['roles'][0]['color']);
        $this->assertCount(1, $scene['seats']);

        // A supervisor who teaches this session stands at the front, still as «ناظر» and in its colour.
        $supervisor = User::query()->where('name', 'ناظر کلاس')->sole();
        $session->teachers()->attach($supervisor->id);
        $scene = app(SessionScene::class)->for($session->fresh());

        $this->assertSame([['مدرس جلسه', 'مدرس'], ['ناظر کلاس', 'ناظر']], $this->people($scene['teachers']));
        $this->assertSame([['پشتیبان دو', 'پشتیبان'], ['پشتیبان یک', 'پشتیبان']], $this->people($scene['staff']));
        $this->assertSame($scene['roles'][1]['color'], $scene['teachers'][1]['color']);
    }

    /**
     * @param  list<array{name: string, role: string, color: string}>  $people
     * @return list<array{0: string, 1: string}>
     */
    private function people(array $people): array
    {
        return array_map(fn (array $person): array => [$person['name'], $person['role']], $people);
    }

    private function member(string $name, ClassRole $role, ?string $leftOn = null): User
    {
        $user = User::factory()->create(['name' => $name]);
        ClassMember::factory()->create(['classroom_id' => $this->classroom->id, 'class_role_id' => $role->id, 'user_id' => $user->id, 'joined_on' => null, 'left_on' => $leftOn]);

        return $user;
    }

    private function attendee(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        ClassMember::factory()->create(['classroom_id' => $this->classroom->id, 'class_role_id' => $this->attendeeRole->id, 'user_id' => $user->id, 'joined_on' => null, 'left_on' => null]);

        return $user;
    }

    private function record(ClassSession $session, User $user, AttendanceStatus $status, ?int $lateMinutes = null): void
    {
        Attendance::factory()->create(['session_id' => $session->id, 'classroom_id' => $session->classroom_id, 'tenant_id' => $session->tenant_id, 'user_id' => $user->id, 'status' => $status, 'late_minutes' => $lateMinutes]);
    }

    private function classSession(SessionStatus $status = SessionStatus::Scheduled): ClassSession
    {
        return ClassSession::factory()->create([
            'classroom_id' => $this->classroom->id,
            'starts_at' => '2026-10-29 10:00',
            'ends_at' => '2026-10-29 11:30',
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

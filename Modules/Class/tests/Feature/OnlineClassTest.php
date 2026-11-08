<?php

namespace Modules\Class\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\OnlinePlatform;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Filament\Resources\Classrooms\Pages\ListClassrooms;
use Modules\Class\Filament\Resources\OnlineServers\OnlineServerResource;
use Modules\Class\Filament\Resources\OnlineServers\Pages\ListOnlineServers;
use Modules\Class\Models\ClassOnlineRoom;
use Modules\Class\Models\ClassOnlineSessionMeeting;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\OnlineServer;
use Modules\Class\Models\OnlineServerAccount;
use Modules\Class\Services\ClassMembers;
use Modules\Class\Services\Online\OnlineRooms;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Online classes on several platforms (ADR 0073). Today is Thursday
 * 2026-10-29, 12:00; the class's sessions are on 2026-11-01 and 2026-11-08
 * at 10:00.
 */
class OnlineClassTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private Classroom $classroom;

    private User $teacher;

    private User $student;

    private ClassSession $first;

    private ClassSession $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));
        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
        $this->roleWithPermissions('member', []);

        $this->classroom = Classroom::factory()->for($this->west)->create(['title' => 'هفتم الف', 'code' => '7A', 'mode' => ClassMode::Online]);
        $members = app(ClassMembers::class);
        $members->add($this->classroom, ClassRole::factory()->for($this->primary)->teacher()->create(), [($this->teacher = $this->person())->id]);
        $members->add($this->classroom, ClassRole::factory()->for($this->primary)->attendee()->create(), [($this->student = $this->person())->id], CarbonImmutable::parse('2026-10-01'));

        $this->first = ClassSession::factory()->create(['classroom_id' => $this->classroom->id, 'starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-01 11:30']);
        $this->second = ClassSession::factory()->create(['classroom_id' => $this->classroom->id, 'starts_at' => '2026-11-08 10:00', 'ends_at' => '2026-11-08 11:30']);
        app(OnlineRooms::class)->change($this->classroom, 'link', 'https://meet.example.com/7a');
    }

    public function test_moving_to_another_platform_keeps_the_earlier_sessions_where_they_were(): void
    {
        $server = OnlineServer::factory()->create(['title' => 'سرور اول']);
        $rooms = app(OnlineRooms::class);

        $rooms->change($this->classroom, 'server:'.$server->id, null, CarbonImmutable::parse('2026-11-05'));

        $this->assertSame(OnlinePlatform::Link, $rooms->forSession($this->fresh($this->first))->platform);
        $this->assertSame($server->id, $rooms->forSession($this->fresh($this->second))->server_id);
        $this->assertSame(['از ابتدا: لینک https://meet.example.com/7a', 'از 1405/08/14: Adobe Connect — سرور اول'], $rooms->history($this->classroom));

        // Without a day, the planned move stays and only today's link changes.
        $rooms->change($this->classroom, 'link', 'https://meet.example.com/new');
        $this->assertSame(2, $this->classroom->onlineRooms()->count());
        $this->assertSame('https://meet.example.com/new', $rooms->forSession($this->fresh($this->first))->meeting_url);

        // Back to the link from an earlier day: the planned room goes.
        $rooms->change($this->classroom, 'link', 'https://meet.example.com/newer', CarbonImmutable::parse('2026-11-03'));
        $this->assertSame(1, $this->classroom->onlineRooms()->count());
        $this->assertSame('https://meet.example.com/newer', $rooms->forSession($this->fresh($this->second))->meeting_url);
    }

    public function test_a_class_without_earlier_sessions_just_meets_on_the_new_platform(): void
    {
        $server = OnlineServer::factory()->create();
        ClassSession::query()->delete();

        app(OnlineRooms::class)->change($this->classroom, 'server:'.$server->id, null, CarbonImmutable::parse('2026-11-05'));

        $room = $this->classroom->onlineRooms()->sole();
        $this->assertSame($server->id, $room->server_id);
        $this->assertNull($room->starts_on);
    }

    public function test_the_class_form_moves_a_class_to_a_server_from_a_day(): void
    {
        $server = OnlineServer::factory()->create();
        $manager = User::factory()->create();
        $this->roleWithPermissions('class_manager', ['ViewAny:Classroom', 'View:Classroom', 'Update:Classroom']);
        $this->makeStaff($manager, $this->west);
        $this->grantRole($manager, $this->west, 'class_manager');

        $this->actingInPanel($manager, $this->west);
        Livewire::test(ListClassrooms::class)
            ->mountTableAction(EditAction::class, $this->classroom)
            ->assertSchemaStateSet(['online_choice' => 'link', 'meeting_url' => 'https://meet.example.com/7a'])
            ->fillForm(['online_choice' => 'server:'.$server->id, 'online_from' => '2026-11-05'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $rooms = $this->classroom->onlineRooms()->orderByRaw('starts_on nulls first')->get();
        $this->assertSame(['link', 'adobe_connect'], $rooms->map(fn (ClassOnlineRoom $room): string => $room->platform->value)->all());
        $this->assertSame('2026-11-05', $rooms->last()->starts_on->toDateString());
        $this->assertSame($manager->id, $rooms->last()->created_by_id);
    }

    public function test_attendees_enter_a_link_from_a_little_before_the_session_and_staff_any_time(): void
    {
        $this->as($this->teacher, $this->first)->assertRedirect('https://meet.example.com/7a');
        $this->refused($this->as($this->student, $this->first), 'کلاس آنلاین از 15 دقیقه پیش از شروع جلسه (10:00) باز می‌شود.');

        $this->travelTo(CarbonImmutable::parse('2026-11-01 09:50'));
        $this->as($this->student, $this->first)->assertRedirect('https://meet.example.com/7a');

        $this->travelTo(CarbonImmutable::parse('2026-11-01 11:31'));
        $this->refused($this->as($this->student, $this->first), 'این جلسه تمام شده است.');
        $this->as($this->teacher, $this->first)->assertRedirect('https://meet.example.com/7a');
    }

    public function test_nobody_enters_a_cancelled_session_or_one_they_are_not_in(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00'));
        $this->refused($this->as($this->person(), $this->first), 'شما در این جلسه نیستید.');

        $this->first->update(['status' => SessionStatus::Cancelled]);
        $this->refused($this->as($this->teacher, $this->first), 'جلسه لغو شده است.');
    }

    public function test_a_session_of_another_tenant_is_not_found(): void
    {
        $outsider = User::factory()->create();
        $this->grantRole($outsider, $this->primary, 'member');

        $this->actingAs($outsider)
            ->withSession([ResolveCurrentTenant::SESSION_KEY => $this->primary->id])
            ->get(route('class.online.enter', $this->first))
            ->assertNotFound();
    }

    public function test_the_session_page_offers_the_way_in_to_the_person_only(): void
    {
        $this->actingAs($this->student)
            ->withSession([ResolveCurrentTenant::SESSION_KEY => $this->west->id])
            ->get(route('my-classes.session', $this->first))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('session.online_platform', 'لینک')
                ->where('session.join_url', route('class.online.enter', $this->first)));
    }

    public function test_adobe_connect_makes_the_room_and_the_accounts_and_signs_people_in(): void
    {
        $server = OnlineServer::factory()->create(['host' => 'https://adobe.example.com/', 'settings' => ['folder_id' => '11003', 'login_prefix' => 'sara']]);
        app(OnlineRooms::class)->change($this->classroom, 'server:'.$server->id, null, CarbonImmutable::parse('2026-11-05'));
        $this->fakeAdobe();

        $this->as($this->teacher, $this->second)->assertRedirect('https://adobe.example.com/class7a/?session=breez2');

        $room = ClassOnlineRoom::query()->whereNotNull('server_id')->sole();
        $this->assertSame('555', $room->external_id);
        $this->assertSame('/class7a/', $room->url_path);
        $this->assertSame('777', OnlineServerAccount::query()->where('user_id', $this->teacher->id)->value('external_id'));
        Http::assertSent(fn (Request $request): bool => $this->action($request) === 'sco-update' && $request['folder-id'] === '11003' && $request['type'] === 'meeting' && str_starts_with($request['name'], 'هفتم الف (7A) #'));
        Http::assertSent(fn (Request $request): bool => $this->action($request) === 'permissions-update' && $request['principal-id'] === 'public-access' && $request['permission-id'] === 'denied');
        Http::assertSent(fn (Request $request): bool => $this->action($request) === 'permissions-update' && $request['principal-id'] === '777' && $request['permission-id'] === 'host');
        Http::assertSent(fn (Request $request): bool => $this->action($request) === 'principal-update' && $request['login'] === 'sara'.$this->teacher->id.'@adobe.example.com' && $request['send-email'] === 'false');

        // The learner, in time: the same room, as a participant.
        $this->travelTo(CarbonImmutable::parse('2026-11-08 09:55'));
        $this->as($this->student, $this->second)->assertRedirect();
        Http::assertSent(fn (Request $request): bool => $this->action($request) === 'permissions-update' && $request['permission-id'] === 'view');
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $this->action($request) === 'sco-update'));

        // The session before the move stays on the link.
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00'));
        $this->as($this->student, $this->first)->assertRedirect('https://meet.example.com/7a');
    }

    public function test_adobe_connect_can_make_a_room_for_each_session(): void
    {
        $server = OnlineServer::factory()->create(['host' => 'https://adobe.example.com']);
        ClassSession::query()->delete();
        $first = ClassSession::factory()->create(['classroom_id' => $this->classroom->id, 'starts_at' => '2026-11-01 10:00', 'ends_at' => '2026-11-01 11:30']);
        $second = ClassSession::factory()->create(['classroom_id' => $this->classroom->id, 'starts_at' => '2026-11-08 10:00', 'ends_at' => '2026-11-08 11:30']);
        app(OnlineRooms::class)->change($this->classroom, 'server:'.$server->id, null, CarbonImmutable::parse('2026-10-30'), perSession: true);
        $this->fakeAdobe();

        $this->as($this->teacher, $first)->assertRedirect('https://adobe.example.com/class7a/?session=breez2');
        $this->as($this->teacher, $first)->assertRedirect();
        $this->as($this->teacher, $second)->assertRedirect('https://adobe.example.com/class7a1/?session=breez4');

        $this->assertSame(['555', '556'], ClassOnlineSessionMeeting::query()->orderBy('session_id')->pluck('external_id')->all());
        $this->assertNull(ClassOnlineRoom::query()->sole()->external_id);
        $this->assertCount(2, Http::recorded(fn (Request $request): bool => $this->action($request) === 'sco-update'));
        Http::assertSent(fn (Request $request): bool => $this->action($request) === 'sco-update' && str_starts_with($request['name'], 'هفتم الف (7A) — 1405/08/10 10:00 #'));
        $this->assertSame(['از ابتدا: Adobe Connect — '.$server->title.' (یک اتاق برای هر جلسه)'], app(OnlineRooms::class)->history($this->classroom));

        // Another tenant doesn't see the session's meetings.
        $this->primary->makeCurrent();
        $this->assertSame(0, ClassOnlineSessionMeeting::query()->count());
    }

    public function test_a_new_class_on_adobe_connect_asks_for_a_room_per_class_or_per_session(): void
    {
        $server = OnlineServer::factory()->create();
        $manager = User::factory()->create();
        $this->roleWithPermissions('class_manager', ['ViewAny:Classroom', 'View:Classroom', 'Create:Classroom', 'Update:Classroom']);
        $this->makeStaff($manager, $this->west);
        $this->grantRole($manager, $this->west, 'class_manager');

        $this->actingInPanel($manager, $this->west);
        Livewire::test(ListClassrooms::class)
            ->mountAction('create')
            ->fillForm(['title' => 'هشتم ب', 'mode' => ClassMode::Online->value, 'online_choice' => 'server:'.$server->id])
            ->assertFormFieldVisible('online_rooms_per')
            ->fillForm(['online_rooms_per' => 'session'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $room = Classroom::query()->where('title', 'هشتم ب')->sole()->onlineRooms()->sole();
        $this->assertTrue($room->per_session);

        // A link has no such choice.
        Livewire::test(ListClassrooms::class)
            ->mountAction('create')
            ->fillForm(['title' => 'هشتم ج', 'mode' => ClassMode::Online->value, 'online_choice' => 'link'])
            ->assertFormFieldHidden('online_rooms_per');
    }

    public function test_adobe_connect_sets_the_password_again_when_signing_in_fails(): void
    {
        $server = OnlineServer::factory()->create();
        app(OnlineRooms::class)->change($this->classroom, 'server:'.$server->id, null, CarbonImmutable::parse('2026-10-30'));
        OnlineServerAccount::query()->create(['server_id' => $server->id, 'user_id' => $this->teacher->id, 'external_id' => '900']);
        $this->fakeAdobe(failFirstUserLogin: true);

        $this->as($this->teacher, $this->second)->assertRedirect();

        Http::assertSent(fn (Request $request): bool => $this->action($request) === 'user-update-pwd' && $request['user-id'] === '900');
    }

    public function test_a_server_that_cant_be_reached_says_so(): void
    {
        $server = OnlineServer::factory()->create();
        app(OnlineRooms::class)->change($this->classroom, 'server:'.$server->id, null, CarbonImmutable::parse('2026-10-30'));
        Http::fake(['*' => Http::response('<html>not adobe</html>')]);

        $this->refused($this->as($this->teacher, $this->second), 'پاسخ سرور Adobe Connect خوانا نبود؛ نشانی سرور را بررسی کنید.');
    }

    public function test_servers_are_managed_with_their_password_encrypted_and_checked(): void
    {
        $admin = User::factory()->create();
        $this->roleWithPermissions('server_admin', ['ViewAny:OnlineServer', 'Create:OnlineServer', 'Update:OnlineServer']);
        $this->makeStaff($admin, $this->primary);
        $this->grantRole($admin, $this->primary, 'server_admin');
        $this->fakeAdobe();

        $this->actingInPanel($admin, $this->primary);
        $this->get(OnlineServerResource::getUrl('index', tenant: $this->primary))->assertOk();
        Livewire::test(ListOnlineServers::class)
            ->callAction('create', data: [
                'platform' => OnlinePlatform::AdobeConnect->value, 'title' => 'سرور اول', 'host' => 'https://adobe.example.com',
                'username' => 'admin1', 'password' => 'Secret#1', 'settings' => ['folder_id' => '11003', 'login_prefix' => 'sara', 'verify_ssl' => true],
            ])
            ->assertHasNoActionErrors();

        $server = OnlineServer::query()->sole();
        $this->assertSame('Secret#1', $server->password);
        $this->assertNotSame('Secret#1', DB::table('online_servers')->value('password'));

        Livewire::test(ListOnlineServers::class)
            ->callTableAction('check', $server)
            ->assertNotified('اتصال به سرور برقرار است.');

        // An empty password on edit keeps the old one.
        Livewire::test(ListOnlineServers::class)
            ->callTableAction(EditAction::class, $server, data: ['title' => 'سرور یک', 'password' => ''])
            ->assertHasNoTableActionErrors();
        $this->assertSame('Secret#1', $server->fresh()->password);
        $this->assertSame('سرور یک', $server->fresh()->title);
    }

    private function fakeAdobe(bool $failFirstUserLogin = false): void
    {
        $sessions = 0;
        $userLogins = 0;
        $meetings = 0;
        $ok = '<results><status code="ok"/></results>';

        Http::fake(['adobe.example.com/*' => function (Request $request) use (&$sessions, &$userLogins, &$meetings, $failFirstUserLogin, $ok) {
            return Http::response(match ($this->action($request)) {
                'common-info' => '<results><status code="ok"/><common><cookie>breez'.(++$sessions).'</cookie></common></results>',
                'login' => $request['login'] !== 'admin' && $failFirstUserLogin && $userLogins++ === 0 ? '<results><status code="no-data"/></results>' : $ok,
                'sco-update' => '<results><status code="ok"/><sco sco-id="'.(555 + $meetings).'" folder-id="11003" type="meeting"><url-path>/class7a'.($meetings++ ?: '').'/</url-path></sco></results>',
                'principal-list' => '<results><status code="ok"/><principal-list/></results>',
                'principal-update' => '<results><status code="ok"/><principal principal-id="777" type="user"/></results>',
                'sco-shortcuts' => '<results><status code="ok"/><shortcuts><sco sco-id="11003" type="meetings"/></shortcuts></results>',
                default => $ok,
            });
        }]);
    }

    private function action(Request $request): ?string
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['action'] ?? null;
    }

    private function refused(TestResponse $response, string $message): void
    {
        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Class::OnlineRefused')->where('message', $message));
    }

    private function as(User $user, ClassSession $session): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([ResolveCurrentTenant::SESSION_KEY => $this->west->id])
            ->get(route('class.online.enter', $session));
    }

    private function fresh(ClassSession $session): ClassSession
    {
        return ClassSession::query()->with('classroom.onlineRooms')->find($session->id);
    }

    private function person(): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->west, 'member');

        return $user;
    }
}

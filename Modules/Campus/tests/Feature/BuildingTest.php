<?php

namespace Modules\Campus\Tests\Feature;

use App\Campus\BuildingRooms;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Campus\Filament\Pages\Building;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The tenant's 3D building (ADR 0051). Now is 2026-10-29 12:00.
 */
class BuildingTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    protected function setUp(): void
    {
        parent::setUp();

        if (! (Module::find('Class')?->isEnabled() && Module::find('Campus')?->isEnabled())) {
            $this->markTestSkipped('The Class or Campus module is off.');
        }

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));

        Tenant::factory()->create()->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
    }

    public function test_each_running_class_is_a_room_with_what_happens_today(): void
    {
        $live = $this->classroom('الف زنده');
        $this->classSession($live, '2026-10-29 11:30', '2026-10-29 13:00');
        $today = $this->classroom('ب امروز', ['mode' => ClassMode::Online, 'capacity' => 20]);
        $this->classSession($today, '2026-10-29 16:00', '2026-10-29 17:00');
        $this->classSession($today, '2026-10-29 08:00', '2026-10-29 09:00');
        $idle = $this->classroom('پ بی‌جلسه');
        $this->classSession($idle, '2026-10-29 12:00', '2026-10-29 13:00', SessionStatus::Cancelled);
        $this->classSession($idle, '2026-10-30 12:00', '2026-10-30 13:00');
        ClassMember::factory()->create(['classroom_id' => $today->id, 'class_role_id' => ClassRole::factory()->create(['is_attendee' => true])->id, 'joined_on' => null, 'left_on' => null]);

        $this->classroom('خاموش', ['is_active' => false]);
        $this->classroom('تمام‌شده', ['ends_on' => '2026-10-28']);
        $this->classroom('زیرکلاس', ['parent_id' => $live->id]);

        $this->actingInPanel($admin = $this->admin(), $this->west);
        $rooms = collect(BuildingRooms::for($admin))->keyBy('title');

        $this->assertSame(['الف زنده', 'ب امروز', 'پ بی‌جلسه'], $rooms->keys()->all());
        $this->assertSame(BuildingRooms::LIVE, $rooms['الف زنده']['state']);
        $this->assertSame('11:30–13:00', $rooms['الف زنده']['live']['time']);
        $this->assertSame(BuildingRooms::TODAY, $rooms['ب امروز']['state']);
        $this->assertSame('16:00', $rooms['ب امروز']['next']);
        $this->assertTrue($rooms['ب امروز']['online']);
        $this->assertSame([1, 20], [$rooms['ب امروز']['people'], $rooms['ب امروز']['capacity']]);
        $this->assertSame(BuildingRooms::IDLE, $rooms['پ بی‌جلسه']['state']);
    }

    public function test_the_page_shows_only_this_tenants_rooms(): void
    {
        $this->classroom('کلاس این واحد');
        Tenant::factory()->create(['name' => 'واحد دیگر'])->execute(fn () => Classroom::factory()->for(Tenant::current())->create(['title' => 'کلاس واحد دیگر']));

        $this->actingInPanel($this->admin(), $this->west);
        $this->get(Building::getUrl(tenant: $this->west))
            ->assertOk()
            ->assertSee('ساختمان '.$this->west->name)
            ->assertSee('کلاس این واحد')
            ->assertSee('هر کلاس یک اتاق است')
            ->assertSee('جاهای کرکره‌دار کلاسی ندارند')
            ->assertDontSee('کلاس واحد دیگر');
    }

    public function test_it_needs_the_right_to_see_classes(): void
    {
        $this->classroom('کلاس');
        $url = Building::getUrl(tenant: $this->west);

        $stranger = User::factory()->create();
        $this->makeStaff($stranger, $this->west);
        $this->actingInPanel($stranger, $this->west);
        $this->get($url)->assertForbidden();

        $this->roleWithPermissions('class_lister', ['ViewAny:Classroom']);
        $lister = User::factory()->create();
        $this->makeStaff($lister, $this->west);
        $this->grantRole($lister, $this->west, 'class_lister');
        $this->actingInPanel($lister, $this->west);
        $this->get($url)->assertOk()->assertSee('کلاس');
        // Lists the rooms, but may not open a class.
        $this->assertNull(BuildingRooms::for($lister)[0]['url']);
    }

    public function test_an_empty_tenant_says_so(): void
    {
        $this->actingInPanel($this->admin(), $this->west);
        $this->get(Building::getUrl(tenant: $this->west))->assertOk()->assertSee('هنوز کلاس فعالی');
    }

    public function test_the_live_data_follows_the_clock(): void
    {
        $classroom = $this->classroom('کلاس');
        $this->classSession($classroom, '2026-10-29 13:00', '2026-10-29 14:00');

        $this->actingInPanel($this->admin(), $this->west);
        $page = Livewire::test(Building::class);

        $this->assertSame(BuildingRooms::TODAY, $page->instance()->liveData()['rooms'][0]['state']);
        $this->assertSame(1, $page->instance()->liveData()['legend'][BuildingRooms::TODAY]['count']);

        $this->travelTo(CarbonImmutable::parse('2026-10-29 13:10'));
        $live = $page->instance()->liveData();
        $this->assertSame(BuildingRooms::LIVE, $live['rooms'][0]['state']);
        $this->assertSame('13:10', $live['time']);
        $this->assertSame(1, $live['legend'][BuildingRooms::LIVE]['count']);

        $this->travelTo(CarbonImmutable::parse('2026-10-29 14:10'));
        $this->assertSame(BuildingRooms::IDLE, $page->instance()->liveData()['rooms'][0]['state']);
    }

    public function test_a_live_request_needs_the_right_still_and_doesnt_render(): void
    {
        $this->classroom('کلاس');
        $this->roleWithPermissions('class_lister', ['ViewAny:Classroom']);
        $lister = User::factory()->create();
        $this->makeStaff($lister, $this->west);
        $this->grantRole($lister, $this->west, 'class_lister');
        $this->actingInPanel($lister, $this->west);

        $page = Livewire::test(Building::class);
        $html = $page->call('liveData')->assertOk()->effects['html'] ?? null;
        $this->assertNull($html);

        $lister->unsetRelation('roles')->removeRole('class_lister');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $page->call('liveData')->assertForbidden();
    }

    public function test_the_page_is_told_how_often_to_update(): void
    {
        $this->classroom('کلاس');
        config(['campus.live_seconds' => 45]);

        $this->actingInPanel($this->admin(), $this->west);
        $this->get(Building::getUrl(tenant: $this->west))->assertOk()->assertSee('keepLive(45', false)->assertSee('خودکار به‌روز می‌شود');

        config(['campus.live_seconds' => 0]);
        $this->get(Building::getUrl(tenant: $this->west))->assertOk()->assertDontSee('خودکار به‌روز می‌شود');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function classroom(string $title, array $attributes = []): Classroom
    {
        return Classroom::factory()->for($this->west)->create(['title' => $title, 'mode' => ClassMode::InPerson, 'is_active' => true, 'ends_on' => null, ...$attributes]);
    }

    private function classSession(Classroom $classroom, string $startsAt, string $endsAt, SessionStatus $status = SessionStatus::Scheduled): ClassSession
    {
        return ClassSession::factory()->create(['classroom_id' => $classroom->id, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'status' => $status]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin(Tenant::primary());

        return $admin;
    }
}

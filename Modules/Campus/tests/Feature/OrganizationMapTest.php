<?php

namespace Modules\Campus\Tests\Feature;

use App\Campus\BuildingRooms;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Campus\Filament\Pages\Building;
use Modules\Campus\Filament\Pages\OrganizationMap;
use Modules\Campus\Services\OrganizationSites;
use Modules\Class\Enums\ClassMode;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The map of the organization's tenants (ADR 0052). Now is 2026-10-29 12:00.
 */
class OrganizationMapTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private Tenant $east;

    protected function setUp(): void
    {
        parent::setUp();

        if (! (Module::find('Class')?->isEnabled() && Module::find('Campus')?->isEnabled())) {
            $this->markTestSkipped('The Class or Campus module is off.');
        }

        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00'));

        $this->primary = Tenant::factory()->create(['name' => 'ستاد']);
        $this->primary->makePrimary();
        $this->primary->refresh();
        $this->west = Tenant::factory()->create(['name' => 'الف غرب']);
        $this->east = Tenant::factory()->create(['name' => 'ب شرق']);

        $this->classroomIn($this->west, 'کلاس محرمانه غرب', live: true);
        $this->classroomIn($this->east, 'کلاس شرق');
        $this->primary->makeCurrent();
    }

    public function test_a_super_admin_sees_every_active_tenant_primary_first(): void
    {
        Tenant::factory()->create(['name' => 'خاموش', 'is_active' => false]);
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);

        $this->actingInPanel($admin, $this->primary);
        $sites = app(OrganizationSites::class)->for($admin);

        $this->assertSame(['ستاد', 'الف غرب', 'ب شرق'], array_column($sites, 'name'));
        $this->assertTrue($sites[0]['primary']);
        $this->assertSame([BuildingRooms::LIVE], array_column($sites[1]['rooms'], 'state'));
        $this->assertSame(1, $sites[1]['live']);
        $this->assertStringContainsString($this->west->slug, $sites[1]['url']);
    }

    public function test_a_role_in_one_tenant_shows_nothing_of_another(): void
    {
        $this->roleWithPermissions('class_lister', ['ViewAny:Classroom']);
        $this->roleWithPermissions('no_classes', ['ViewAny:User']);
        $person = User::factory()->create();
        $this->makeStaff($person, $this->west);
        $this->grantRole($person, $this->west, 'class_lister');
        $this->makeStaff($person, $this->east);
        $this->grantRole($person, $this->east, 'no_classes');

        $this->actingInPanel($person, $this->east);

        $this->assertSame(['الف غرب'], array_column(app(OrganizationSites::class)->for($person), 'name'));
        // Asked from a tenant without the right, the map still opens with the one that has it.
        $this->get(OrganizationMap::getUrl(tenant: $this->east))
            ->assertOk()
            ->assertSee('الف غرب')
            ->assertDontSee('ب شرق');
    }

    public function test_someone_who_may_not_list_classes_anywhere_cant_open_it(): void
    {
        $person = User::factory()->create();
        $this->makeStaff($person, $this->west);

        $this->actingInPanel($person, $this->west);
        $this->get(OrganizationMap::getUrl(tenant: $this->west))->assertForbidden();
    }

    public function test_the_map_has_no_class_names_and_keeps_the_current_tenant(): void
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin($this->primary);

        $this->actingInPanel($admin, $this->east);
        $this->get(OrganizationMap::getUrl(tenant: $this->east))
            ->assertOk()
            ->assertSee(Building::getUrl(tenant: $this->west), false)
            ->assertDontSee('کلاس محرمانه غرب');

        $this->assertSame($this->east->getKey(), Tenant::current()->getKey());
        $this->assertSame($this->east->getKey(), getPermissionsTeamId());
    }

    public function test_the_live_data_has_only_the_users_tenants_and_no_class_names(): void
    {
        $this->roleWithPermissions('class_lister', ['ViewAny:Classroom']);
        $person = User::factory()->create();
        $this->makeStaff($person, $this->west);
        $this->grantRole($person, $this->west, 'class_lister');
        $this->actingInPanel($person, $this->west);

        $live = Livewire::test(OrganizationMap::class)->instance()->liveData();

        $this->assertSame(['الف غرب'], array_column($live['sites'], 'name'));
        $this->assertSame(['rooms' => 1, 'live' => 1, 'people' => 0], $live['totals']);
        $this->assertStringNotContainsString('کلاس محرمانه غرب', json_encode($live, JSON_UNESCAPED_UNICODE));

        $this->travelTo(CarbonImmutable::parse('2026-10-29 13:10'));
        $this->assertSame(0, Livewire::test(OrganizationMap::class)->instance()->liveData()['totals']['live']);
    }

    private function classroomIn(Tenant $tenant, string $title, bool $live = false): void
    {
        $tenant->execute(function () use ($tenant, $title, $live): void {
            $classroom = Classroom::factory()->for($tenant)->create(['title' => $title, 'mode' => ClassMode::InPerson, 'is_active' => true, 'ends_on' => null]);

            if ($live) {
                ClassSession::factory()->create(['classroom_id' => $classroom->id, 'starts_at' => '2026-10-29 11:30', 'ends_at' => '2026-10-29 13:00']);
            }
        });
    }
}

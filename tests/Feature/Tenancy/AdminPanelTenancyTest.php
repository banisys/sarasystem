<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AdminPanelTenancyTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_staff_can_open_the_panel_of_their_own_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $this->makeStaff($user, $tenant);

        $this->actingAs($user)->get("/cpadmin/{$tenant->slug}")->assertOk();

        $this->assertTrue($tenant->isCurrent());
        $this->assertSame($tenant->id, getPermissionsTeamId());
    }

    public function test_staff_cannot_open_another_tenants_panel(): void
    {
        [$own, $other] = Tenant::factory()->count(2)->create();
        $user = User::factory()->create();
        $this->makeStaff($user, $own);

        $this->actingAs($user)->get("/cpadmin/{$other->slug}")->assertNotFound();
    }

    public function test_users_without_a_staff_role_cannot_enter_the_panel(): void
    {
        $tenant = Tenant::factory()->create();
        $parent = User::factory()->create();
        $this->grantRole($parent, $tenant, 'parent');

        $this->actingAs($parent)->get("/cpadmin/{$tenant->slug}")->assertForbidden();
    }

    public function test_the_tenant_switcher_lists_only_the_users_staff_tenants(): void
    {
        [$staffIn, $parentIn, $unrelated] = Tenant::factory()->count(3)->create();
        $user = User::factory()->create();
        $this->makeStaff($user, $staffIn);
        $this->grantRole($user, $parentIn, 'parent');

        $tenants = $user->getTenants(Filament::getPanel('admin'));

        $this->assertSame([$staffIn->id], $tenants->pluck('id')->all());
    }

    public function test_super_admin_can_manage_roles_from_inside_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $this->grantRole(User::factory()->create(), $tenant, 'teacher');

        $this->actingAs($admin)->get("/cpadmin/{$tenant->slug}/shield/roles")->assertOk()->assertSee('Teacher');
        $this->actingAs($admin)->get("/cpadmin/{$tenant->slug}/shield/roles/create")->assertOk();
    }

    public function test_the_panel_uses_the_project_font_and_ui_hooks(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get("/cpadmin/{$tenant->slug}/users")
            ->assertOk()
            ->assertSee('--font-family: \'Shabnam\'', escape: false)
            ->assertSee('fi-nav-search-input', escape: false)
            ->assertSee('fi-ta-top-scroll', escape: false);
    }

    public function test_super_admin_can_open_any_active_tenant_but_not_inactive_ones(): void
    {
        $active = Tenant::factory()->create();
        $inactive = Tenant::factory()->inactive()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get("/cpadmin/{$active->slug}")->assertOk();
        $this->actingAs($admin)->get("/cpadmin/{$inactive->slug}")->assertNotFound();
    }
}

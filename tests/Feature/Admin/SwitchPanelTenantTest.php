<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SwitchPanelTenantTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    protected function setUp(): void
    {
        parent::setUp();

        $this->west = Tenant::factory()->create(['slug' => 'west']);
    }

    public function test_switching_keeps_the_current_page(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/cpadmin/west/switch-tenant?page=users')
            ->assertRedirect('/cpadmin/west/users');
    }

    public function test_panel_pages_retarget_the_tenant_menu(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/cpadmin/west/users')
            ->assertOk()
            ->assertSee("/switch-tenant?page='", false);
    }

    public function test_a_record_page_falls_back_to_its_list(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/cpadmin/west/switch-tenant?page='.urlencode('users/5/edit'))
            ->assertRedirect('/cpadmin/west/users');
    }

    public function test_a_page_the_user_cannot_open_there_falls_back_to_the_dashboard(): void
    {
        $staff = User::factory()->create();
        $this->makeStaff($staff, $this->west);

        $this->actingAs($staff)
            ->get('/cpadmin/west/switch-tenant?page=users')
            ->assertRedirect('/cpadmin/west');
    }

    public function test_a_create_page_needs_the_create_permission_there(): void
    {
        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $this->west);
        $this->roleWithPermissions('viewer', ['ViewAny:User']);
        $this->grantRole($viewer, $this->west, 'viewer');

        $this->actingAs($viewer)
            ->get('/cpadmin/west/switch-tenant?page='.urlencode('users/create'))
            ->assertRedirect('/cpadmin/west/users');
    }

    public function test_unknown_or_escaping_paths_go_to_the_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/cpadmin/west/switch-tenant?page=nothing-here')->assertRedirect('/cpadmin/west');
        $this->actingAs($admin)->get('/cpadmin/west/switch-tenant?page='.urlencode('../../login'))->assertRedirect('/cpadmin/west');
    }

    public function test_switching_to_a_tenant_the_user_cannot_access_is_refused(): void
    {
        $staff = User::factory()->create();
        $this->makeStaff($staff, Tenant::factory()->create());

        $this->actingAs($staff)->get('/cpadmin/west/switch-tenant?page=users')->assertNotFound();
    }
}

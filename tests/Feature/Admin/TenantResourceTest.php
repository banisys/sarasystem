<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Tenants\Pages\ManageTenants;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantResourceTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_super_admin_can_list_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get("/cpadmin/{$tenant->slug}/tenants")->assertOk();
    }

    public function test_staff_cannot_open_tenant_management_even_with_every_user_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = User::factory()->create();
        $this->makeStaff($manager, $tenant);
        $this->roleWithPermissions('manager', ['ViewAny:User', 'Create:User', 'Update:User']);
        $this->grantRole($manager, $tenant, 'manager');

        $this->actingAs($manager)->get("/cpadmin/{$tenant->slug}/tenants")->assertForbidden();
    }

    public function test_super_admin_creates_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        Livewire::test(ManageTenants::class)
            ->callAction(CreateAction::class, ['name' => 'شعبه مرزداران', 'slug' => 'marzdaran-1', 'is_active' => true])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tenants', ['slug' => 'marzdaran-1', 'name' => 'شعبه مرزداران']);
    }

    public function test_slug_must_be_url_safe_and_unique(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'taken']);
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        Livewire::test(ManageTenants::class)
            ->callAction(CreateAction::class, ['name' => 'x', 'slug' => 'Not Valid'])
            ->assertHasFormErrors(['slug' => 'regex']);

        Livewire::test(ManageTenants::class)
            ->callAction(CreateAction::class, ['name' => 'x', 'slug' => 'taken'])
            ->assertHasFormErrors(['slug' => 'unique']);
    }
}

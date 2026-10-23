<?php

namespace Tests\Feature\Tenancy;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantRolesTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_a_user_holds_different_roles_in_different_tenants(): void
    {
        [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
        $user = User::factory()->create();

        $this->grantRole($user, $tenantA, 'teacher');
        $this->grantRole($user, $tenantB, 'manager');

        $tenantA->makeCurrent();
        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole('teacher'));
        $this->assertFalse($user->hasRole('manager'));

        $tenantB->makeCurrent();
        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole('manager'));
        $this->assertFalse($user->hasRole('teacher'));
    }

    public function test_making_a_tenant_current_switches_the_permission_team(): void
    {
        $tenant = Tenant::factory()->create();

        $tenant->makeCurrent();
        $this->assertSame($tenant->id, getPermissionsTeamId());

        Tenant::forgetCurrent();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_tenants_lists_every_tenant_the_user_has_a_role_in(): void
    {
        [$tenantA, $tenantB, $other] = Tenant::factory()->count(3)->create();
        $user = User::factory()->create();

        $this->grantRole($user, $tenantA, 'teacher');
        $this->grantRole($user, $tenantA, 'supporter');
        $this->grantRole($user, $tenantB, 'parent');

        $this->assertEqualsCanonicalizing([$tenantA->id, $tenantB->id], $user->tenants()->pluck('tenants.id')->all());
        $this->assertFalse($user->belongsToTenant($other));
    }

    public function test_super_admin_passes_every_gate_check(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('anything-at-all'));
        $this->assertFalse(Gate::forUser($user)->allows('anything-at-all'));
    }

    public function test_the_super_admin_role_counts_in_every_tenant(): void
    {
        [$branch, $other] = Tenant::factory()->count(2)->create();
        $user = User::factory()->create();

        $user->makeSuperAdmin($branch);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->canAccessTenant($other));
        $this->assertTrue($other->execute(fn () => Gate::forUser($user)->allows('anything-at-all')));
    }

    public function test_system_roles_cannot_be_deleted_or_renamed(): void
    {
        $role = Role::findByName(Role::SUPER_ADMIN, 'web');

        $role->delete();
        $role->update(['name' => 'renamed']);

        $this->assertTrue($role->fresh()->exists);
        $this->assertSame(Role::SUPER_ADMIN, $role->fresh()->name);
    }
}

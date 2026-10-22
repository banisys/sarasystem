<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WebTenantSelectionTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_a_user_with_one_tenant_gets_it_automatically(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $this->grantRole($user, $tenant, 'student');

        $this->actingAs($user)->get('/')
            ->assertSessionHas(ResolveCurrentTenant::SESSION_KEY, $tenant->id)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tenant.current.id', $tenant->id));
    }

    public function test_a_user_with_several_tenants_must_choose_one(): void
    {
        [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
        $user = User::factory()->create();
        $this->grantRole($user, $tenantA, 'parent');
        $this->grantRole($user, $tenantB, 'parent');

        $this->actingAs($user)->get('/')
            ->assertSessionMissing(ResolveCurrentTenant::SESSION_KEY)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tenant.current', null)
                ->has('tenant.available', 2));

        $this->post("/tenants/{$tenantB->slug}/switch")->assertRedirect();

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('tenant.current.id', $tenantB->id));
    }

    public function test_switching_to_a_tenant_the_user_does_not_belong_to_is_forbidden(): void
    {
        [$own, $other] = Tenant::factory()->count(2)->create();
        $user = User::factory()->create();
        $this->grantRole($user, $own, 'student');

        $this->actingAs($user)->post("/tenants/{$other->slug}/switch")->assertForbidden();
    }

    public function test_a_stale_tenant_in_the_session_is_dropped(): void
    {
        [$own, $other] = Tenant::factory()->count(2)->create();
        $user = User::factory()->create();
        $this->grantRole($user, $own, 'student');

        $this->actingAs($user)
            ->withSession([ResolveCurrentTenant::SESSION_KEY => $other->id])
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tenant.current.id', $own->id));
    }
}

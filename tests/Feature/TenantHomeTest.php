<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TenantHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_root_is_the_primary_tenants_home_page(): void
    {
        $primary = Tenant::factory()->create(['name' => 'ستاد']);
        $primary->makePrimary();
        Tenant::factory()->create(['name' => 'شعبه غرب', 'slug' => 'west']);
        Tenant::factory()->inactive()->create(['name' => 'بسته']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tenants/Home')
                ->where('site.tenant.name', 'ستاد')
                ->where('site.otherTenants', [['name' => 'شعبه غرب', 'url' => url('/west')]]));
    }

    public function test_each_tenant_has_a_home_page_at_its_slug(): void
    {
        Tenant::factory()->create()->makePrimary();
        Tenant::factory()->create(['name' => 'شعبه غرب', 'slug' => 'west']);

        $this->get('/west')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tenants/Home')
                ->where('site.tenant.name', 'شعبه غرب')
                ->where('site.tenant.url', url('/west')));
    }

    public function test_the_primary_tenants_slug_redirects_to_the_site_root(): void
    {
        Tenant::factory()->create(['slug' => 'head-office'])->makePrimary();

        $this->get('/head-office')->assertRedirect(route('home'));
    }

    public function test_inactive_and_unknown_tenants_have_no_home_page(): void
    {
        Tenant::factory()->inactive()->create(['slug' => 'closed']);

        $this->get('/closed')->assertNotFound();
        $this->get('/no-such-tenant')->assertNotFound();
    }

    public function test_without_a_primary_tenant_the_root_shows_the_welcome_page(): void
    {
        Tenant::factory()->create();

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome'));
    }

    public function test_only_one_tenant_is_primary(): void
    {
        [$first, $second] = Tenant::factory()->count(2)->create();

        $first->makePrimary();
        $second->makePrimary();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertTrue(Tenant::primary()->is($second));
    }

    public function test_route_segments_and_public_directories_are_reserved_slugs(): void
    {
        $reserved = Tenant::reservedSlugs();

        foreach (['cpadmin', 'login', 'dashboard', 'profile', 'tenants', 'build', 'up'] as $slug) {
            $this->assertContains($slug, $reserved);
        }
        $this->assertNotContains('west', $reserved);
    }
}

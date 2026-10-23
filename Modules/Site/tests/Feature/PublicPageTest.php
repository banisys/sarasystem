<?php

namespace Modules\Site\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Site\Models\Page;
use Tests\TestCase;

class PublicPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = Tenant::factory()->create(['name' => 'ستاد', 'slug' => 'head-office']);
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'شعبه غرب', 'slug' => 'west']);
    }

    public function test_the_primary_tenants_pages_are_served_from_the_root(): void
    {
        Page::factory()->for($this->primary)->create(['title' => 'درباره‌ی ما', 'path' => '/about-us']);

        $this->get('/about-us')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Site::Page')
                ->where('page.title', 'درباره‌ی ما')
                ->where('site.tenant.name', 'ستاد'));

        $this->get('/head-office/about-us')->assertRedirect(url('/about-us'));
    }

    public function test_other_tenants_pages_are_served_under_their_slug(): void
    {
        Page::factory()->for($this->west)->create(['title' => 'تماس', 'path' => '/contact/map']);

        $this->get('/west/contact/map')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('page.title', 'تماس')
                ->where('site.tenant.name', 'شعبه غرب'));
    }

    public function test_a_tenants_pages_are_not_served_on_another_tenants_site(): void
    {
        Page::factory()->for($this->west)->create(['path' => '/about-us']);

        $this->get('/about-us')->assertNotFound();
        $this->get('/west/about-us')->assertOk();
    }

    public function test_every_tenant_gets_a_published_home_page(): void
    {
        $home = Page::query()->withoutGlobalScopes()->where('tenant_id', $this->west->id)->sole();

        $this->assertTrue($home->isHome());
        $this->assertSame('شعبه غرب', $home->title);
        $this->assertTrue($home->is_published);
    }

    public function test_the_home_page_is_served_at_the_tenants_root(): void
    {
        Page::withoutTenantScope()->where('tenant_id', $this->west->id)->update(['title' => 'خانه‌ی غرب']);

        $this->get('/west')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Site::Page')->where('page.title', 'خانه‌ی غرب'));
    }

    public function test_an_unpublished_home_page_falls_back_to_the_default_one(): void
    {
        Page::withoutTenantScope()->where('tenant_id', $this->primary->id)->update(['is_published' => false]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->component('Tenants/Home')->where('site.tenant.name', 'ستاد'));
    }

    public function test_the_home_page_cannot_be_deleted(): void
    {
        $home = Page::withoutTenantScope()->where('tenant_id', $this->west->id)->sole();

        $this->assertFalse($home->delete());
        $this->assertModelExists($home);
    }

    public function test_drafts_and_unknown_paths_are_not_found(): void
    {
        Page::factory()->for($this->primary)->draft()->create(['path' => '/draft']);

        $this->get('/draft')->assertNotFound();
        $this->get('/west/nothing-here')->assertNotFound();
    }

    public function test_paths_are_normalized_and_checked(): void
    {
        $this->assertSame('/about-us/team', Page::normalizePath(' About-Us//team/ '));
        $this->assertSame('/', Page::normalizePath(''));

        $this->assertNull(Page::pathProblem('/about-us', $this->west));
        $this->assertNotNull(Page::pathProblem('/', $this->west), 'the home page exists');
        $this->assertNotNull(Page::pathProblem('/درباره', $this->west));
        $this->assertNotNull(Page::pathProblem('/login', $this->west), 'app route');
        $this->assertNotNull(Page::pathProblem('/cpadmin/x', $this->west), 'app route');
        $this->assertNotNull(Page::pathProblem('/west/x', $this->primary), 'tenant slug');

        Page::factory()->for($this->west)->create(['path' => '/about-us']);
        $this->assertNotNull(Page::pathProblem('/about-us', $this->west), 'taken in this tenant');
        $this->assertNull(Page::pathProblem('/about-us', $this->primary), 'free in another tenant');
    }

    public function test_a_page_path_reserves_its_first_segment_as_a_tenant_slug(): void
    {
        Page::factory()->for($this->west)->create(['path' => '/news/2026']);

        $this->assertContains('news', Tenant::reservedSlugs());
    }
}

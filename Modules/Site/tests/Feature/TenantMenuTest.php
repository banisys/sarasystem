<?php

namespace Modules\Site\Tests\Feature;

use App\Filament\Resources\Tenants\Pages\ManageTenants;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Modules\Site\Models\Menu;
use Modules\Site\Models\MenuItem;
use Modules\Site\Models\Page;
use Modules\Site\Models\TenantMenu;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TenantMenuTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $headOffice;

    private Tenant $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headOffice = Tenant::factory()->create();
        $this->headOffice->makePrimary();
        $this->branch = Tenant::factory()->create(['slug' => 'west']);
    }

    public function test_a_super_admin_chooses_a_head_office_menu_for_a_branch_in_the_tenants_list(): void
    {
        $menu = Menu::factory()->for($this->headOffice)->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->headOffice);

        Livewire::test(ManageTenants::class)
            ->callAction(TestAction::make('siteMenu')->table($this->branch), ['menu_id' => $menu->id])
            ->assertHasNoActionErrors();

        $this->assertTrue(TenantMenu::menuOf($this->branch)->is($menu));

        Livewire::test(ManageTenants::class)
            ->mountAction(TestAction::make('siteMenu')->table($this->branch))
            ->assertActionDataSet(['menu_id' => $menu->id])
            ->fillForm(['menu_id' => null])
            ->callMountedAction();

        $this->assertNull(TenantMenu::menuOf($this->branch));
    }

    public function test_the_site_header_shows_the_tenants_menu_with_visible_working_links_only(): void
    {
        $menu = Menu::factory()->for($this->headOffice)->create();
        $about = Page::factory()->for($this->headOffice)->create(['path' => '/about-us']);
        $draft = Page::factory()->for($this->headOffice)->draft()->create();

        $group = MenuItem::factory()->for($menu)->create(['title' => 'درباره', 'link_type' => 'none', 'url' => null, 'sort' => 0]);
        MenuItem::factory()->childOf($group)->linkingTo($about)->create(['title' => 'معرفی', 'sort' => 0]);
        MenuItem::factory()->childOf($group)->linkingTo($draft)->create(['title' => 'پیش‌نویس', 'sort' => 1]);
        MenuItem::factory()->childOf($group)->create(['title' => 'بد', 'url' => 'javascript:alert(1)', 'sort' => 2]);
        $hidden = MenuItem::factory()->for($menu)->hidden()->create(['title' => 'مخفی', 'sort' => 1]);
        MenuItem::factory()->childOf($hidden)->create(['title' => 'زیر مخفی']);
        MenuItem::factory()->for($menu)->create(['title' => 'آپارات', 'url' => 'https://aparat.com', 'open_in_new_tab' => true, 'sort' => 2]);

        TenantMenu::assign($this->branch, $menu->id);

        $expected = [
            ['title' => 'درباره', 'url' => null, 'new_tab' => false, 'children' => [
                ['title' => 'معرفی', 'url' => url('/about-us'), 'new_tab' => false, 'children' => []],
            ]],
            ['title' => 'آپارات', 'url' => 'https://aparat.com', 'new_tab' => true, 'children' => []],
        ];

        // The branch's own page and its default home page alike.
        Page::withoutTenantScope()->where('tenant_id', $this->branch->id)->where('path', '/')->update(['is_published' => false]);
        Page::factory()->for($this->branch)->create(['path' => '/contact']);

        foreach (['/west/contact', '/west'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->where('site.navigation', $expected));
        }

        // The head office has no menu chosen.
        $this->get('/about-us')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('site.navigation', []));
    }

    public function test_deleting_a_menu_removes_it_from_the_sites(): void
    {
        $menu = Menu::factory()->for($this->headOffice)->create();
        TenantMenu::assign($this->branch, $menu->id);

        $menu->delete();

        $this->assertNull(TenantMenu::menuOf($this->branch));
    }
}

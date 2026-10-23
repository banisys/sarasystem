<?php

namespace Modules\Site\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Site\Filament\Resources\SiteMenus\MenuResource;
use Modules\Site\Filament\Resources\SiteMenus\Pages\ListMenus;
use Modules\Site\Filament\Resources\SiteMenus\Pages\MenuItems;
use Modules\Site\Models\Menu;
use Modules\Site\Models\MenuItem;
use Modules\Site\Models\Page;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MenuResourceTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->otherTenant] = Tenant::factory()->count(2)->create();

        $this->roleWithPermissions('site_editor', ['ViewAny:Menu', 'View:Menu', 'Create:Menu', 'Update:Menu', 'Delete:Menu']);

        $this->editor = User::factory()->create();
        $this->makeStaff($this->editor, $this->tenant);
        $this->grantRole($this->editor, $this->tenant, 'site_editor');
    }

    public function test_the_list_shows_only_the_current_tenants_menus(): void
    {
        $own = Menu::factory()->for($this->tenant)->create();
        $foreign = Menu::factory()->for($this->otherTenant)->create();

        $this->actingInPanel($this->editor, $this->tenant);

        Livewire::test(ListMenus::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_another_tenants_menu_cannot_be_opened(): void
    {
        $foreign = Menu::factory()->for($this->otherTenant)->create();

        $this->actingAs($this->editor)
            ->get(MenuResource::getUrl('items', ['record' => $foreign], tenant: $this->tenant))
            ->assertNotFound();
    }

    public function test_an_editor_creates_a_menu_in_the_current_tenant_and_goes_to_its_items(): void
    {
        $this->actingInPanel($this->editor, $this->tenant);

        Livewire::test(ListMenus::class)
            ->callAction('create', ['title' => 'منوی بالا'])
            ->assertHasNoActionErrors()
            ->assertRedirectContains('/items');

        $this->assertDatabaseHas('site_menus', ['tenant_id' => $this->tenant->id, 'title' => 'منوی بالا']);
    }

    public function test_an_editor_adds_items_and_submenus(): void
    {
        $menu = Menu::factory()->for($this->tenant)->create();
        $page = Page::factory()->for($this->tenant)->create(['title' => 'درباره', 'path' => '/about-us']);
        $this->actingInPanel($this->editor, $this->tenant);

        $component = Livewire::test(MenuItems::class, ['record' => $menu->getRouteKey()])
            ->callAction('addItem', ['title' => 'درباره‌ی ما', 'link_type' => 'page', 'page_id' => $page->id])
            ->assertHasNoActionErrors();

        $parent = $menu->items()->sole();

        $component
            ->callAction(TestAction::make('addItem')->arguments(['parent' => $parent->id]), ['title' => 'سایت قدیمی', 'link_type' => 'url', 'url' => 'https://example.com', 'open_in_new_tab' => true])
            ->assertHasNoActionErrors()
            ->callAction(TestAction::make('addItem')->arguments(['parent' => $parent->id]), ['title' => 'ورود', 'link_type' => 'url', 'url' => '/login'])
            ->assertHasNoActionErrors()
            ->assertSee(['درباره‌ی ما', 'سایت قدیمی', 'ورود']);

        $this->assertSame(['سایت قدیمی', 'ورود'], $parent->children()->pluck('title')->all());
        $this->assertDatabaseHas('site_menu_items', ['title' => 'درباره‌ی ما', 'tenant_id' => $this->tenant->id, 'page_id' => $page->id, 'url' => null, 'parent_id' => null]);
        $this->assertDatabaseHas('site_menu_items', ['title' => 'سایت قدیمی', 'parent_id' => $parent->id, 'open_in_new_tab' => true, 'sort' => 0]);
        $this->assertDatabaseHas('site_menu_items', ['title' => 'ورود', 'sort' => 1]);
    }

    public function test_unsafe_addresses_are_rejected(): void
    {
        $menu = Menu::factory()->for($this->tenant)->create();
        $this->actingInPanel($this->editor, $this->tenant);

        Livewire::test(MenuItems::class, ['record' => $menu->getRouteKey()])
            ->callAction('addItem', ['title' => 'بد', 'link_type' => 'url', 'url' => 'javascript:alert(1)'])
            ->assertHasActionErrors(['url']);

        $this->assertSame(0, $menu->items()->count());
    }

    public function test_an_editor_edits_and_deletes_an_item_with_its_submenu(): void
    {
        $menu = Menu::factory()->for($this->tenant)->create();
        $item = MenuItem::factory()->for($menu)->create(['title' => 'قدیمی']);
        $child = MenuItem::factory()->childOf($item)->create();
        $this->actingInPanel($this->editor, $this->tenant);

        Livewire::test(MenuItems::class, ['record' => $menu->getRouteKey()])
            ->callAction(TestAction::make('editItem')->arguments(['item' => $item->id]), ['title' => 'تازه', 'link_type' => 'none'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('site_menu_items', ['id' => $item->id, 'title' => 'تازه', 'link_type' => 'none', 'url' => null]);

        Livewire::test(MenuItems::class, ['record' => $menu->getRouteKey()])
            ->callAction(TestAction::make('deleteItem')->arguments(['item' => $item->id]));

        $this->assertDatabaseMissing('site_menu_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('site_menu_items', ['id' => $child->id]);
    }

    public function test_items_are_reordered_and_moved_under_another_item(): void
    {
        $menu = Menu::factory()->for($this->tenant)->create();
        [$a, $b, $c] = MenuItem::factory()->for($menu)->count(3)->sequence(['sort' => 0], ['sort' => 1], ['sort' => 2])->create();
        $this->actingInPanel($this->editor, $this->tenant);

        $component = Livewire::test(MenuItems::class, ['record' => $menu->getRouteKey()])
            ->call('moveItem', $c->id, null, [$c->id, $a->id, $b->id]);

        $this->assertSame([$c->id, $a->id, $b->id], $menu->itemTree()->pluck('id')->all());

        $component->call('moveItem', $b->id, $a->id, [$b->id]);

        $this->assertSame($a->id, $b->fresh()->parent_id);
        $this->assertSame([$c->id, $a->id], $menu->itemTree()->pluck('id')->all());

        // Not under its own submenu.
        $component->call('moveItem', $a->id, $b->id, [$a->id]);

        $this->assertNull($a->fresh()->parent_id);
    }

    public function test_items_of_another_menu_or_tenant_cannot_be_moved_or_changed(): void
    {
        $menu = Menu::factory()->for($this->tenant)->create();
        $own = MenuItem::factory()->for($menu)->create();
        $otherMenuItem = MenuItem::factory()->for(Menu::factory()->for($this->tenant))->create();
        $foreignItem = MenuItem::factory()->for(Menu::factory()->for($this->otherTenant))->create(['title' => 'خارجی']);
        $this->actingInPanel($this->editor, $this->tenant);

        Livewire::test(MenuItems::class, ['record' => $menu->getRouteKey()])
            ->call('moveItem', $otherMenuItem->id, $own->id, [$otherMenuItem->id])
            ->call('moveItem', $foreignItem->id, $own->id, [$foreignItem->id])
            ->call('moveItem', $own->id, $foreignItem->id, [$own->id]);

        foreach (['editItem', 'deleteItem'] as $action) {
            Livewire::test(MenuItems::class, ['record' => $menu->getRouteKey()])
                ->mountAction(TestAction::make($action)->arguments(['item' => $foreignItem->id]))
                ->assertNotFound();
        }

        $this->assertNull($otherMenuItem->fresh()->parent_id);
        $this->assertSame($foreignItem->menu_id, MenuItem::withoutTenantScope()->find($foreignItem->id)->menu_id);
        $this->assertNull(MenuItem::withoutTenantScope()->find($foreignItem->id)->parent_id);
        $this->assertNull($own->fresh()->parent_id);
        $this->assertModelExists($menu);
    }

    public function test_viewers_without_update_permission_cannot_change_items(): void
    {
        $this->roleWithPermissions('menu_viewer', ['ViewAny:Menu', 'View:Menu']);
        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $this->tenant);
        $this->grantRole($viewer, $this->tenant, 'menu_viewer');
        $menu = Menu::factory()->for($this->tenant)->create();
        [$a, $b] = MenuItem::factory()->for($menu)->count(2)->sequence(['sort' => 0], ['sort' => 1])->create();
        $this->actingInPanel($viewer, $this->tenant);

        Livewire::test(MenuItems::class, ['record' => $menu->getRouteKey()])
            ->assertActionHidden('addItem')
            ->call('moveItem', $b->id, $a->id, [$b->id])
            ->assertForbidden();

        $this->assertNull($b->fresh()->parent_id);
    }

    public function test_staff_without_menu_permissions_cannot_open_the_list(): void
    {
        $teacher = User::factory()->create();
        $this->makeStaff($teacher, $this->tenant);

        $this->actingAs($teacher)->get(MenuResource::getUrl('index', tenant: $this->tenant))->assertForbidden();
    }
}

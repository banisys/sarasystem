<?php

namespace Modules\Site\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Site\Filament\Resources\SitePages\PageResource;
use Modules\Site\Filament\Resources\SitePages\Pages\CreatePage;
use Modules\Site\Filament\Resources\SitePages\Pages\EditPage;
use Modules\Site\Filament\Resources\SitePages\Pages\ListPages;
use Modules\Site\Models\Page;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PageResourceTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->otherTenant] = Tenant::factory()->count(2)->create();

        $this->roleWithPermissions('site_editor', ['ViewAny:Page', 'View:Page', 'Create:Page', 'Update:Page', 'Delete:Page']);

        $this->editor = User::factory()->create();
        $this->makeStaff($this->editor, $this->tenant);
        $this->grantRole($this->editor, $this->tenant, 'site_editor');
    }

    public function test_the_list_shows_only_the_current_tenants_pages(): void
    {
        $own = Page::factory()->for($this->tenant)->create();
        $foreign = Page::factory()->for($this->otherTenant)->create();

        $this->actingInPanel($this->editor, $this->tenant);

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_another_tenants_page_cannot_be_opened(): void
    {
        $foreign = Page::factory()->for($this->otherTenant)->create();

        $this->actingAs($this->editor)
            ->get(PageResource::getUrl('edit', ['record' => $foreign], tenant: $this->tenant))
            ->assertNotFound();
    }

    public function test_an_editor_creates_a_page_with_a_normalized_path(): void
    {
        $this->actingInPanel($this->editor, $this->tenant);

        Livewire::test(CreatePage::class)
            ->fillForm(['tenant_id' => $this->tenant->id, 'title' => 'درباره‌ی ما', 'path' => 'About-Us/', 'is_published' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('site_pages', ['tenant_id' => $this->tenant->id, 'path' => '/about-us', 'is_published' => true]);
    }

    public function test_reserved_and_duplicate_paths_are_rejected(): void
    {
        Page::factory()->for($this->tenant)->create(['path' => '/about-us']);
        $this->actingInPanel($this->editor, $this->tenant);

        foreach (['/login', '/'.$this->otherTenant->slug, '/about-us'] as $path) {
            Livewire::test(CreatePage::class)
                ->fillForm(['tenant_id' => $this->tenant->id, 'title' => 'x', 'path' => $path])
                ->call('create')
                ->assertHasFormErrors(['path']);
        }
    }

    public function test_pages_can_only_be_created_in_tenants_where_the_user_may_create_them(): void
    {
        $this->makeStaff($this->editor, $this->otherTenant);
        $this->actingInPanel($this->editor, $this->tenant);

        $this->assertSame([$this->tenant->id], array_keys(PageResource::creatableTenantOptions()));

        Livewire::test(CreatePage::class)
            ->fillForm(['tenant_id' => $this->otherTenant->id, 'title' => 'x', 'path' => '/x'])
            ->call('create')
            ->assertHasFormErrors(['tenant_id']);

        $this->assertDatabaseMissing('site_pages', ['path' => '/x']);
    }

    public function test_a_super_admin_creates_a_page_for_another_tenant(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingInPanel($admin, $this->tenant);

        Livewire::test(CreatePage::class)
            ->fillForm(['tenant_id' => $this->otherTenant->id, 'title' => 'x', 'path' => '/x'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirectContains("/cpadmin/{$this->otherTenant->slug}/");

        $this->assertDatabaseHas('site_pages', ['tenant_id' => $this->otherTenant->id, 'path' => '/x']);
    }

    public function test_editing_keeps_the_tenant_and_checks_the_path(): void
    {
        $page = Page::factory()->for($this->tenant)->create(['path' => '/a']);
        Page::factory()->for($this->tenant)->create(['path' => '/b']);
        $this->actingInPanel($this->editor, $this->tenant);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['path' => '/b'])
            ->call('save')
            ->assertHasFormErrors(['path']);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['path' => '/a', 'title' => 'تازه'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('تازه', $page->fresh()->title);
        $this->assertSame($this->tenant->id, $page->fresh()->tenant_id);
    }

    public function test_staff_without_page_permissions_cannot_open_the_list(): void
    {
        $teacher = User::factory()->create();
        $this->makeStaff($teacher, $this->tenant);

        $this->actingAs($teacher)->get(PageResource::getUrl('index', tenant: $this->tenant))->assertForbidden();
    }

    public function test_the_view_action_links_to_the_public_page(): void
    {
        $page = Page::factory()->for($this->tenant)->create(['path' => '/about-us']);
        $this->actingInPanel($this->editor, $this->tenant);

        Livewire::test(ListPages::class)
            ->assertActionHasUrl(TestAction::make('view')->table($page), url("/{$this->tenant->slug}/about-us"));
    }
}

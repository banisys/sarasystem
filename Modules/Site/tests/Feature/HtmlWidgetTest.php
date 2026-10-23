<?php

namespace Modules\Site\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Modules\Site\Filament\Resources\SitePages\PageResource;
use Modules\Site\Filament\Resources\SitePages\Pages\EditPage;
use Modules\Site\Filament\Resources\SitePages\Pages\ListPages;
use Modules\Site\Filament\Resources\SitePages\Pages\PageBuilder;
use Modules\Site\Models\Page;
use Modules\Site\Widgets\HtmlWidget;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class HtmlWidgetTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_unsafe_html_is_removed_and_safe_html_kept(): void
    {
        $clean = HtmlWidget::sanitize(
            '<h2 class="title">سلام</h2><p style="color:red">متن <a href="https://example.com">لینک</a></p>'
            .'<script>alert(1)</script><img src="https://example.com/a.png" onerror="alert(1)">'
            .'<a href="javascript:alert(1)">بد</a>'
            .'<iframe src="https://www.aparat.com/video/video/embed/x" allowfullscreen></iframe>'
            .'<iframe src="http://insecure.example/x"></iframe>',
        );

        $this->assertStringContainsString('<h2 class="title">سلام</h2>', $clean);
        $this->assertStringContainsString('style="color:red"', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('src="https://www.aparat.com/video/video/embed/x"', $clean);
        $this->assertStringContainsString('src="https://example.com/a.png"', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('http://insecure.example', $clean);
    }

    public function test_the_page_shows_its_widgets_in_order_and_sanitized(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makePrimary();
        Page::factory()->for($tenant)->create([
            'path' => '/about-us',
            'content' => [
                ['type' => 'html', 'data' => ['html' => '<p>اول</p><script>alert(1)</script>']],
                ['type' => 'removed-widget', 'data' => []],
                ['type' => 'html', 'data' => ['html' => '<p>دوم</p>']],
            ],
        ]);

        $this->get('/about-us')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Site::Page')
                ->where('widgets', [
                    ['component' => 'Html', 'props' => ['html' => '<p>اول</p>']],
                    ['component' => 'Html', 'props' => ['html' => '<p>دوم</p>']],
                ]));
    }

    public function test_an_editor_adds_an_html_widget_and_it_is_saved_sanitized(): void
    {
        Builder::fake();
        $tenant = Tenant::factory()->create();
        $this->roleWithPermissions('site_editor', ['ViewAny:Page', 'View:Page', 'Update:Page']);
        $editor = User::factory()->create();
        $this->makeStaff($editor, $tenant);
        $this->grantRole($editor, $tenant, 'site_editor');
        $page = Page::factory()->for($tenant)->create();
        $this->actingInPanel($editor, $tenant);

        // Widgets are edited in the page builder only, reached from the list.
        $this->get(PageResource::getUrl('builder', ['record' => $page], tenant: $tenant))
            ->assertOk()
            ->assertSee('افزودن ویجت');
        $this->get(PageResource::getUrl('edit', ['record' => $page], tenant: $tenant))
            ->assertOk()
            ->assertDontSee('افزودن ویجت')
            ->assertDontSee(PageResource::getUrl('builder', ['record' => $page], tenant: $tenant));
        Livewire::test(ListPages::class)
            ->assertActionHasUrl(TestAction::make('builder')->table($page), PageResource::getUrl('builder', ['record' => $page], tenant: $tenant));

        Livewire::test(PageBuilder::class, ['record' => $page->getRouteKey()])
            ->fillForm(['content' => [
                ['type' => 'html', 'data' => ['html' => '<p onclick="x()">خوش آمدید</p>']],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals([['type' => 'html', 'data' => ['html' => '<p>خوش آمدید</p>']]], $page->fresh()->content);
    }

    public function test_saving_the_page_settings_keeps_the_widgets(): void
    {
        $tenant = Tenant::factory()->create();
        $this->roleWithPermissions('site_editor', ['ViewAny:Page', 'View:Page', 'Update:Page']);
        $editor = User::factory()->create();
        $this->makeStaff($editor, $tenant);
        $this->grantRole($editor, $tenant, 'site_editor');
        $content = [['type' => 'html', 'data' => ['html' => '<p>بماند</p>']]];
        $page = Page::factory()->for($tenant)->create(['content' => $content]);
        $this->actingInPanel($editor, $tenant);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'عنوان تازه'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('عنوان تازه', $page->fresh()->title);
        $this->assertEquals($content, $page->fresh()->content);
    }
}

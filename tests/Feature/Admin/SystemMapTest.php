<?php

namespace Tests\Feature\Admin;

use App\Features\SystemMap;
use App\Filament\Pages\SystemMap as SystemMapPage;
use App\Filament\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SystemMapTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $tenant = Tenant::factory()->create();

        $this->get("/cpadmin/{$tenant->slug}/system-map")->assertRedirect(route('login'));
    }

    public function test_staff_cannot_open_the_installation_map(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create();
        $this->makeStaff($staff, $tenant);

        $this->actingAs($staff)->get("/cpadmin/{$tenant->slug}/system-map")->assertForbidden();
    }

    public function test_super_admins_see_the_map_and_current_tenant_screen_links(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();

        foreach ([$first, $second] as $tenant) {
            $this->actingAs($admin)->get("/cpadmin/{$tenant->slug}/system-map")
                ->assertOk()
                ->assertSee(['نقشهٔ سامانه', 'مستقل از شعبه', 'بازسازی نقشه', 'ارتباط بخش‌ها در کار روزمره'])
                ->assertSee(UserResource::getUrl(tenant: $tenant), escape: false);
        }
    }

    public function test_new_modules_appear_without_map_metadata_and_text_is_escaped(): void
    {
        $directory = sys_get_temp_dir().'/system-map-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory.'/FutureFeature', recursive: true);
        File::put($directory.'/FutureFeature/module.json', json_encode([
            'name' => 'FutureFeature',
            'description' => '<script>alert("unsafe")</script>',
        ]));
        File::makeDirectory($directory.'/NamedFeature');
        File::put($directory.'/NamedFeature/module.json', json_encode([
            'name' => 'NamedFeature',
            'description' => '',
            'sitemap' => ['label' => 'پروندهٔ :learners'],
        ]));
        config(['sarasystem.learner_plural_label' => 'هنرجویان']);
        Module::addLocation($directory);
        $this->actingInPanel(User::factory()->superAdmin()->create(), Tenant::factory()->create());

        try {
            Livewire::test(SystemMapPage::class)->assertOk()
                ->assertSee(['FutureFeature', 'سایر امکانات', 'پروندهٔ هنرجویان'])
                ->assertSee('&lt;script&gt;', escape: false)
                ->assertDontSee('<script>alert("unsafe")</script>', escape: false);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_disabled_modules_keep_their_card_but_have_no_screen_links_or_work_connections(): void
    {
        $statuses = tempnam(sys_get_temp_dir(), 'system-map-statuses');
        copy(base_path('modules_statuses.json'), $statuses);
        config(['modules.activators.file.statuses-file' => $statuses]);
        $this->app->forgetInstance(ActivatorInterface::class);
        $this->app->forgetInstance(RepositoryInterface::class);
        Module::clearResolvedInstance('modules');
        $this->actingInPanel(User::factory()->superAdmin()->create(), Tenant::factory()->create());

        try {
            foreach (Module::all() as $module) {
                $module->disable();
                $map = app(SystemMap::class)->build();
                $disabled = $map['modules'][$module->getName()];

                $this->assertSame([], $disabled['screens']);
                foreach ($map['connections'] as $connection) {
                    $this->assertNotSame($disabled['label'], $connection['from']);
                    $this->assertNotSame($disabled['label'], $connection['to']);
                }
            }

            $map = app(SystemMap::class)->build();

            $this->assertSame([], $map['connections']);
            foreach ($map['modules'] as $module) {
                $this->assertFalse($module['enabled']);
                $this->assertSame([], $module['screens']);
            }
            Livewire::test(SystemMapPage::class)->assertOk()->assertSee('غیرفعال');
        } finally {
            unlink($statuses);
        }
    }
}

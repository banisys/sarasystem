<?php

namespace Tests\Feature\Admin;

use App\Features\ModuleChangeRefused;
use App\Features\ModuleManager;
use App\Filament\Pages\ManageModules;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ManageModulesTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private string $statusesFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Work on a copy, so switching modules never touches the real file.
        $this->statusesFile = tempnam(sys_get_temp_dir(), 'modules_statuses');
        copy(base_path('modules_statuses.json'), $this->statusesFile);
        config(['modules.activators.file.statuses-file' => $this->statusesFile]);

        $this->app->forgetInstance(ActivatorInterface::class);
        $this->app->forgetInstance(RepositoryInterface::class);
        Module::clearResolvedInstance('modules');

        Process::fake();
    }

    protected function tearDown(): void
    {
        @unlink($this->statusesFile);

        parent::tearDown();
    }

    private function isEnabled(string $name): bool
    {
        return Module::find($name)->isEnabled();
    }

    public function test_only_super_admins_can_open_the_page(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = User::factory()->create();
        $this->makeStaff($manager, $tenant);

        $this->actingAs($manager)->get("/cpadmin/{$tenant->slug}/modules")->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->get("/cpadmin/{$tenant->slug}/modules")->assertOk();
    }

    public function test_the_list_shows_every_module_with_its_requirements(): void
    {
        $modules = app(ModuleManager::class)->all();

        $this->assertSame(['Class', 'Student'], $modules['School']['requires']);
        $this->assertSame(['School'], array_intersect($modules['Class']['required_by'], ['School']));
        $this->assertTrue($modules['Contract']['enabled']);
    }

    public function test_a_module_nothing_needs_can_be_switched_off_and_on(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingInPanel(User::factory()->superAdmin()->create(), $tenant);

        Livewire::test(ManageModules::class)->callTableAction('disable', 'Contract');
        $this->assertFalse($this->isEnabled('Contract'));

        Livewire::test(ManageModules::class)->callTableAction('enable', 'Contract');
        $this->assertTrue($this->isEnabled('Contract'));

        Process::assertRan(fn ($process): bool => in_array('module:migrate', $process->command, true));
        Process::assertRan(fn ($process): bool => in_array('shield:generate', $process->command, true));
        Process::assertRan(fn ($process): bool => in_array('roles:install-defaults', $process->command, true));
    }

    public function test_a_module_another_enabled_module_needs_cannot_be_switched_off(): void
    {
        $this->actingInPanel(User::factory()->superAdmin()->create(), Tenant::factory()->create());

        Livewire::test(ManageModules::class)->callTableAction('disable', 'Class');

        $this->assertTrue($this->isEnabled('Class'));
        $this->expectException(ModuleChangeRefused::class);
        app(ModuleManager::class)->setEnabled('Class', false);
    }

    public function test_a_module_whose_requirements_are_off_cannot_be_switched_on(): void
    {
        $manager = app(ModuleManager::class);
        $manager->setEnabled('School', false);
        $manager->setEnabled('Student', false);

        try {
            $manager->setEnabled('School', true);
            $this->fail('School was switched on without Student.');
        } catch (ModuleChangeRefused $exception) {
            $this->assertStringContainsString('Student', $exception->getMessage());
        }

        $this->assertFalse($this->isEnabled('School'));
    }

    public function test_a_failed_preparation_leaves_the_module_off(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);
        $manager = app(ModuleManager::class);
        $manager->setEnabled('Contract', false);

        try {
            $manager->setEnabled('Contract', true);
            $this->fail('The module was switched on although its preparation failed.');
        } catch (ModuleChangeRefused) {
            $this->assertFalse($this->isEnabled('Contract'));
        }
    }
}

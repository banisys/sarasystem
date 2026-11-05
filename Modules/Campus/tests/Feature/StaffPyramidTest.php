<?php

namespace Modules\Campus\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Campus\Services\StaffHierarchy;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Filament\Resources\Employees\Pages\ListEmployees;
use Modules\Employee\Models\Employee;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * A tenant's staff as a pyramid of power (ADR 0058).
 */
class StaffPyramidTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    private Tenant $east;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (! (Module::find('Campus')?->isEnabled() && Module::find('Employee')?->isEnabled())) {
            $this->markTestSkipped('The Campus or Employee module is off.');
        }

        Tenant::factory()->create()->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->east = Tenant::factory()->create();
        $this->west->makeCurrent();

        $this->admin = User::factory()->create();
        $this->admin->makeSuperAdmin(Tenant::primary());

        $this->roleWithPermissions('manager', ['ViewAny:Employee', 'View:Employee', 'Update:Employee']);
        $this->roleWithPermissions('viewer', ['ViewAny:Employee']);
        $this->actingInPanel($this->admin, $this->west);
    }

    public function test_people_stand_by_what_they_can_do_here_and_equals_share_a_column(): void
    {
        $this->employee('مدیر', $this->west, ['manager']);
        $this->employee('الف', $this->west, ['viewer']);
        $this->employee('ب', $this->west, ['viewer']);
        $this->employee('معلم', $this->west, []);

        $groups = $this->powerGroups();

        $this->assertSame(2, $groups['Manager']['level']);
        $this->assertSame(['مدیر'], array_column($groups['Manager']['people'], 'name'));
        // Each person is drawn with their own roles, without the door to the panel.
        $this->assertSame(['Manager'], $groups['Manager']['people'][0]['roles']);
        $this->assertSame(1, $groups['Viewer']['level']);
        $this->assertSame(['الف', 'ب'], array_column($groups['Viewer']['people'], 'name'));
        $this->assertSame(2, $groups['Viewer']['holders']);
        $this->assertSame([$groups['Manager']['id']], $groups['Viewer']['parents']);
        $this->assertSame(0, $groups['بدون دسترسی در پنل']['level']);
    }

    public function test_roles_elsewhere_and_other_tenants_people_dont_count(): void
    {
        $person = $this->employee('دو شعبه', $this->west, ['viewer']);
        $this->makeStaff($person, $this->east);
        $this->grantRole($person, $this->east, 'manager');
        $this->employee('کارمند شرق', $this->east, ['manager']);

        $groups = $this->powerGroups();

        $this->assertSame(['Viewer'], array_keys($groups));
        $this->assertSame(['دو شعبه'], array_column($groups['Viewer']['people'], 'name'));
    }

    public function test_without_panel_access_a_role_gives_no_power(): void
    {
        $person = User::factory()->create(['name' => 'بی‌پنل']);
        $this->grantRole($person, $this->west, 'manager');
        Employee::factory()->create(['tenant_id' => $this->west->getKey(), 'user_id' => $person->getKey()]);

        $this->assertSame(0, $this->powerGroups()['بدون دسترسی در پنل']['permissions']);
    }

    public function test_a_super_admin_is_at_the_top_and_those_who_left_are_gone(): void
    {
        $this->employee('رفته', $this->west, ['manager'], ['status' => EmploymentStatus::Left]);
        $this->employee('الف', $this->west, ['manager']);
        Employee::factory()->create(['tenant_id' => $this->west->getKey(), 'user_id' => $this->admin->getKey()]);

        $scene = app(StaffHierarchy::class)->scene($this->admin);

        $this->assertTrue($scene['roles'][0]['super']);
        $this->assertSame(1, $scene['roles'][0]['level']);
        $this->assertSame(['الف'], array_column($scene['roles'][1]['people'], 'name'));
    }

    public function test_the_button_needs_the_right_to_list_employees(): void
    {
        $this->employee('الف', $this->west, ['viewer']);

        Livewire::test(ListEmployees::class)
            ->assertActionVisible('staffPyramid')
            ->mountAction('staffPyramid')
            ->assertMountedActionModalSee('الف');

        $viewer = $this->employee('بیننده', $this->west, ['viewer']);
        $this->actingInPanel($viewer, $this->west);
        $this->assertSame(['الف', 'بیننده'], array_column(app(StaffHierarchy::class)->scene($viewer)['roles'][0]['people'], 'name'));
        // May list people, but may not open them.
        $this->assertNull(app(StaffHierarchy::class)->scene($viewer)['roles'][0]['people'][0]['url']);

        $stranger = User::factory()->create();
        $this->makeStaff($stranger, $this->west);
        $this->actingInPanel($stranger, $this->west);
        $this->assertSame([], app(StaffHierarchy::class)->scene($stranger)['roles']);
    }

    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $attributes
     */
    private function employee(string $name, Tenant $tenant, array $roles, array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->makeStaff($user, $tenant);
        foreach ($roles as $role) {
            $this->grantRole($user, $tenant, $role);
        }
        Employee::factory()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), ...$attributes]);

        return $user;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function powerGroups(): array
    {
        return collect(app(StaffHierarchy::class)->scene($this->admin)['roles'])->keyBy('label')->all();
    }
}

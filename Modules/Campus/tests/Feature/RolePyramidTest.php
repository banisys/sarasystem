<?php

namespace Modules\Campus\Tests\Feature;

use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Campus\Services\RoleHierarchy;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The roles' pyramid of power (ADR 0057).
 */
class RolePyramidTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $west;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Module::find('Campus')?->isEnabled()) {
            $this->markTestSkipped('The Campus module is off.');
        }

        Tenant::factory()->create()->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->west->makeCurrent();
    }

    public function test_the_order_comes_from_the_permissions(): void
    {
        $this->role('manager', ['View:A', 'Update:A', 'View:B', 'View:C']);
        $this->role('officer', ['View:A', 'Update:A']);
        $this->role('twin', ['View:A', 'Update:A']);
        $this->role('finance', ['View:B']);
        $this->role('reader', ['View:A']);
        $this->role('nobody', []);
        $this->role(Role::SUPER_ADMIN, []);

        $roles = $this->rolesByName();

        // Without permissions it gives nothing in the admin panel, so it isn't shown or counted.
        $this->assertArrayNotHasKey('nobody', $roles);
        $this->assertSame(0, $roles['reader']['level']);
        $this->assertSame(1, $roles['officer']['level']);
        $this->assertSame(1, $roles['twin']['level']);
        $this->assertSame(2, $roles['manager']['level']);
        $this->assertSame(3, $roles[Role::SUPER_ADMIN]['level']);

        // Only the nearest are joined; the rest follow through them.
        $this->assertSame([$roles['manager']['id']], $roles['officer']['parents']);
        $this->assertSame([$roles['twin']['id']], $roles['officer']['peerIds']);
        $this->assertContains($roles[Role::SUPER_ADMIN]['id'], $roles['officer']['aboveIds']);

        // A role that shares nothing with another has no line to it, even if it's lower.
        $this->assertNotContains($roles['finance']['id'], $roles['officer']['belowIds']);
        $this->assertContains($roles['manager']['id'], $roles['finance']['parents']);
    }

    public function test_a_super_admin_has_everything_and_is_above_a_role_with_all_permissions(): void
    {
        $this->role('all', ['View:A', 'View:B']);
        $this->role(Role::SUPER_ADMIN, []);

        $roles = $this->rolesByName();

        $this->assertSame(2, $roles[Role::SUPER_ADMIN]['permissions']);
        $this->assertSame(100, $roles[Role::SUPER_ADMIN]['share']);
        $this->assertSame([], $roles['all']['peerIds']);
        $this->assertSame([$roles[Role::SUPER_ADMIN]['id']], $roles['all']['parents']);
    }

    public function test_it_counts_people_once_and_names_the_areas(): void
    {
        $this->role('officer', ['View:A', 'Update:A', 'View:Classroom']);
        $east = Tenant::factory()->create();
        $person = User::factory()->create();
        $this->grantRole($person, $this->west, 'officer');
        $this->grantRole($person, $east, 'officer');
        $this->grantRole(User::factory()->create(), $east, 'officer');

        $officer = $this->rolesByName()['officer'];

        $this->assertSame(2, $officer['holders']);
        $this->assertSame(['label' => 'A', 'count' => 2], $officer['areas'][0]);
    }

    public function test_the_button_opens_the_pyramid_for_a_super_admin(): void
    {
        $this->role('officer', ['View:A']);
        $admin = User::factory()->create();
        $admin->makeSuperAdmin(Tenant::primary());
        $this->actingInPanel($admin, $this->west);

        Livewire::test(ListRoles::class)
            ->assertActionVisible('rolePyramid')
            ->mountAction('rolePyramid')
            ->assertMountedActionModalSee('هرم قدرت نقش‌ها')
            ->assertMountedActionModalSee('سطح 1');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function role(string $name, array $permissions): Role
    {
        $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web', 'tenant_id' => null]);
        $role->syncPermissions(array_map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'), $permissions));

        return $role;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rolesByName(): array
    {
        $names = Role::query()->pluck('name', 'id');
        $this->actingInPanelForUrls();

        return collect(app(RoleHierarchy::class)->scene()['roles'])
            ->keyBy(fn (array $role): string => $names[$role['id']])
            ->all();
    }

    private function actingInPanelForUrls(): void
    {
        $admin = User::factory()->create();
        $admin->makeSuperAdmin(Tenant::primary());
        $this->actingInPanel($admin, $this->west);
    }
}

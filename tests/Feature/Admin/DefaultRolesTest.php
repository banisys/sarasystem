<?php

namespace Tests\Feature\Admin;

use App\Authorization\DefaultRoles;
use App\Models\Role;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DefaultRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_makes_the_default_roles_with_labels_and_permissions(): void
    {
        $this->artisan('roles:install-defaults')->assertSuccessful();

        $roles = Role::query()->whereNull('tenant_id')->pluck('label', 'name');
        $this->assertSame('مدیر ستاد', $roles['org_admin']);
        $this->assertSame('مدیر '.config('sarasystem.tenant_label'), $roles['tenant_manager']);
        $this->assertSame(config('sarasystem.learner_label'), $roles['learner']);
        $this->assertSame(config('sarasystem.guardian_role_label'), $roles[config('sarasystem.guardian_role')]);

        $this->assertTrue(Role::findByName('finance_officer')->hasPermissionTo('Update:Contract'));
        $this->assertTrue(Role::findByName('registrar')->hasPermissionTo('Create:Student'));
        $this->assertTrue(Role::findByName('education_officer')->hasPermissionTo('Update:Classroom'));
        $this->assertSame([], Role::findByName('instructor')->permissions->pluck('name')->all());
        $this->assertSame([], Role::findByName('learner')->permissions->pluck('name')->all());
    }

    public function test_every_role_has_the_permissions_of_the_roles_below_it(): void
    {
        $defaults = app(DefaultRoles::class);
        $manager = $defaults->permissionsFor(DefaultRoles::TENANT_MANAGER);
        $orgAdmin = $defaults->permissionsFor(DefaultRoles::ORG_ADMIN);

        foreach ([DefaultRoles::EDUCATION_OFFICER, DefaultRoles::REGISTRAR, DefaultRoles::FINANCE_OFFICER] as $officer) {
            $this->assertNotEmpty($defaults->permissionsFor($officer));
            $this->assertSame([], array_diff($defaults->permissionsFor($officer), $manager), "{$officer} must be below tenant_manager.");
        }

        $this->assertSame([], array_diff($manager, $orgAdmin));
        $this->assertContains('Update:Workflow', $orgAdmin);
        $this->assertNotContains('Update:Workflow', $manager);
    }

    public function test_install_leaves_existing_roles_as_they_are_unless_asked(): void
    {
        app(DefaultRoles::class)->install();
        $registrar = Role::findByName('registrar');
        $registrar->update(['label' => 'پذیرش']);
        $registrar->revokePermissionTo('Delete:Student');

        $this->assertSame([], app(DefaultRoles::class)->install());
        $this->assertSame('پذیرش', $registrar->fresh()->label);
        $this->assertFalse($registrar->fresh()->hasPermissionTo('Delete:Student'));

        $this->artisan('roles:install-defaults --add-missing-permissions')->assertSuccessful();
        $this->assertSame('پذیرش', $registrar->fresh()->label);
        $this->assertTrue($registrar->fresh()->hasPermissionTo('Delete:Student'));
    }

    public function test_install_uses_permissions_that_exist_and_makes_the_missing_ones(): void
    {
        $existing = Permission::create(['name' => 'Delete:Student', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->getPermissions();

        app(DefaultRoles::class)->install();

        $this->assertSame(1, Permission::query()->where('name', 'Delete:Student')->count());
        $this->assertTrue(Role::findByName('registrar')->permissions->contains($existing));
        $this->assertTrue(Role::findByName('registrar')->hasPermissionTo('Create:Student'), 'A permission just made must be in the permission cache.');
        $this->assertNotNull(Permission::query()->where('name', 'Create:Student')->value('created_at'));
    }

    public function test_install_does_not_run_a_query_per_permission(): void
    {
        $permissions = count(app(DefaultRoles::class)->permissionsFor(DefaultRoles::ORG_ADMIN));
        $this->assertGreaterThan(100, $permissions);

        DB::enableQueryLog();
        app(DefaultRoles::class)->install();

        $this->assertLessThan($permissions, count(DB::getQueryLog()));
    }

    public function test_default_roles_are_system_roles(): void
    {
        app(DefaultRoles::class)->install();
        $role = Role::findByName('instructor');

        $this->assertTrue($role->isSystemRole());
        $this->assertFalse($role->delete());
        $this->assertFalse($role->update(['name' => 'teacher']));
        $this->assertTrue($role->fresh()->update(['label' => 'معلم']));

        $tenantRole = (new Role)->forceFill(['name' => 'instructor', 'guard_name' => 'web', 'tenant_id' => Tenant::factory()->create()->id]);
        $tenantRole->save();
        $this->assertFalse($tenantRole->isSystemRole(), 'Only the global role is the default one.');
    }

    public function test_the_seeder_installs_the_default_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            [],
            array_diff(app(DefaultRoles::class)->names(), Role::query()->whereNull('tenant_id')->pluck('name')->all()),
        );
    }
}

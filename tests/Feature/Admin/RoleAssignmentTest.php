<?php

namespace Tests\Feature\Admin;

use App\Authorization\RoleAssignment;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * The permission-subset rule (docs/decisions/0003-role-assignment-rules.md).
 */
class RoleAssignmentTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private const MANAGER_PERMISSIONS = ['ViewAny:User', 'View:User', 'Create:User', 'Update:User'];

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $manager;

    private Role $teacher;

    private Role $director;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->otherTenant] = Tenant::factory()->count(2)->create();

        $this->roleWithPermissions('manager', self::MANAGER_PERMISSIONS);
        $this->roleWithPermissions('deputy', self::MANAGER_PERMISSIONS);
        $this->teacher = $this->roleWithPermissions('teacher', []);
        $this->director = $this->roleWithPermissions('director', [...self::MANAGER_PERMISSIONS, 'Delete:User']);

        $this->manager = User::factory()->create();
        $this->makeStaff($this->manager, $this->tenant);
        $this->grantRole($this->manager, $this->tenant, 'manager');
    }

    public function test_only_roles_within_the_actors_permissions_are_assignable(): void
    {
        $assignable = RoleAssignment::assignableRoles($this->manager, $this->tenant)->pluck('name')->sort()->values()->all();

        $this->assertSame(['deputy', 'manager', Utils::getPanelUserRoleName(), 'teacher'], $assignable);
    }

    public function test_permissions_held_in_another_tenant_do_not_count(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->otherTenant, 'director');
        $this->grantRole($user, $this->tenant, 'teacher');

        // panel_user and teacher carry no permissions, so they are within any set.
        $this->assertSame([Utils::getPanelUserRoleName(), 'teacher'], RoleAssignment::assignableRoles($user, $this->tenant)->pluck('name')->sort()->values()->all());
    }

    public function test_super_admin_may_assign_every_role(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertContains('director', RoleAssignment::assignableRoles($admin, $this->tenant)->pluck('name')->all());
    }

    public function test_a_user_with_a_stronger_role_in_any_tenant_cannot_be_managed(): void
    {
        $lowerHere = User::factory()->create();
        $this->grantRole($lowerHere, $this->tenant, 'teacher');
        $strongerElsewhere = User::factory()->create();
        $this->grantRole($strongerElsewhere, $this->tenant, 'teacher');
        $this->grantRole($strongerElsewhere, $this->otherTenant, 'director');

        $this->assertTrue(RoleAssignment::canManage($this->manager, $lowerHere, $this->tenant));
        $this->assertFalse(RoleAssignment::canManage($this->manager, $strongerElsewhere, $this->tenant));
        $this->assertFalse(RoleAssignment::canManage($this->manager, User::factory()->superAdmin()->create(), $this->tenant));
    }

    public function test_the_edit_page_of_a_stronger_user_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->tenant, 'teacher');
        $this->grantRole($user, $this->otherTenant, 'director');

        $this->actingAs($this->manager)->get("/cpadmin/{$this->tenant->slug}/users/{$user->id}/edit")->assertForbidden();
    }

    public function test_a_stronger_role_cannot_be_given_through_a_tampered_create_request(): void
    {
        $this->actingInPanel($this->manager, $this->tenant);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'x',
                'email' => 'x@example.com',
                'password' => 'secret-password',
                'roles' => [$this->teacher->id, $this->director->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles']);

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_a_stronger_role_cannot_be_given_to_an_existing_user(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->otherTenant, 'teacher');
        $this->actingInPanel($this->manager, $this->tenant);

        Livewire::test(ListUsers::class)
            ->callAction('addExistingUser', ['user_id' => $user->id, 'roles' => [$this->director->id]])
            ->assertHasFormErrors(['roles']);

        $this->assertFalse($this->tenant->execute(fn () => $user->unsetRelation('roles')->hasRole('director')));
    }

    public function test_nobody_but_a_super_admin_changes_their_own_roles(): void
    {
        $this->actingInPanel($this->manager, $this->tenant);

        Livewire::test(EditUser::class, ['record' => $this->manager->getRouteKey()])
            ->assertFormFieldIsDisabled('roles');
    }

    public function test_role_definitions_are_managed_by_the_super_admin_only(): void
    {
        $this->actingAs($this->manager)->get("/cpadmin/{$this->tenant->slug}/shield/roles")->assertForbidden();
    }
}

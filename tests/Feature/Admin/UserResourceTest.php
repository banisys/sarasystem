<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->otherTenant] = Tenant::factory()->count(2)->create();

        $this->roleWithPermissions('manager', ['ViewAny:User', 'View:User', 'Create:User', 'Update:User']);
        $this->roleWithPermissions('teacher', []);

        $this->manager = User::factory()->create();
        $this->makeStaff($this->manager, $this->tenant);
        $this->grantRole($this->manager, $this->tenant, 'manager');
    }

    public function test_the_list_shows_only_members_of_the_current_tenant(): void
    {
        $member = User::factory()->create();
        $this->grantRole($member, $this->tenant, 'teacher');
        $outsider = User::factory()->create();
        $this->grantRole($outsider, $this->otherTenant, 'teacher');

        $this->actingInPanel($this->manager, $this->tenant);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$member, $this->manager])
            ->assertCanNotSeeTableRecords([$outsider]);
    }

    public function test_the_list_is_narrowed_by_text_filters(): void
    {
        $sara = User::factory()->create(['name' => 'Sara Ahmadi', 'mobile' => '09121234567']);
        $this->grantRole($sara, $this->tenant, 'teacher');
        $reza = User::factory()->create(['name' => 'Reza Karimi', 'mobile' => '09359876543']);
        $this->grantRole($reza, $this->tenant, 'teacher');

        $this->actingInPanel($this->manager, $this->tenant);

        Livewire::test(ListUsers::class)
            ->filterTable('name', ['value' => 'sara'])
            ->assertCanSeeTableRecords([$sara])
            ->assertCanNotSeeTableRecords([$reza]);

        Livewire::test(ListUsers::class)
            ->filterTable('mobile', ['value' => '۰۹۳۵'])
            ->assertCanSeeTableRecords([$reza])
            ->assertCanNotSeeTableRecords([$sara]);
    }

    public function test_staff_without_the_user_permission_cannot_open_the_list(): void
    {
        $staff = User::factory()->create();
        $this->makeStaff($staff, $this->tenant);

        $this->actingAs($staff)->get("/cpadmin/{$this->tenant->slug}/users")->assertForbidden();
    }

    public function test_a_user_of_another_tenant_cannot_be_opened_by_url(): void
    {
        $outsider = User::factory()->create();
        $this->grantRole($outsider, $this->otherTenant, 'teacher');

        $this->actingAs($this->manager)->get("/cpadmin/{$this->tenant->slug}/users/{$outsider->id}/edit")->assertNotFound();
    }

    public function test_creating_a_user_gives_roles_in_the_current_tenant_only(): void
    {
        $this->actingInPanel($this->manager, $this->tenant);
        $teacher = Role::findByName('teacher', 'web');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'معلم جدید',
                'email' => 'new.teacher@example.com',
                'password' => 'secret-password',
                'roles' => [$teacher->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'new.teacher@example.com')->firstOrFail();
        $this->assertEquals([$this->tenant->id], $user->tenants()->pluck('tenants.id')->all());
        $this->assertFalse($user->is_super_admin);
    }

    public function test_editing_roles_leaves_the_users_roles_in_other_tenants_alone(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->tenant, 'teacher');
        $this->grantRole($user, $this->otherTenant, 'teacher');
        $this->actingInPanel($this->manager, $this->tenant);
        $manager = Role::findByName('manager', 'web');

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['roles' => [$manager->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['manager'], $this->roleNamesIn($user, $this->tenant));
        $this->assertSame(['teacher'], $this->roleNamesIn($user, $this->otherTenant));
    }

    public function test_a_manager_cannot_edit_a_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->grantRole($admin, $this->tenant, 'teacher');

        $this->actingAs($this->manager)->get("/cpadmin/{$this->tenant->slug}/users/{$admin->id}/edit")->assertForbidden();
    }

    public function test_a_manager_cannot_make_anyone_super_admin(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->tenant, 'teacher');
        $this->actingInPanel($this->manager, $this->tenant);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertFormFieldIsHidden('is_super_admin');
    }

    public function test_removing_from_the_tenant_keeps_other_tenants(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->tenant, 'teacher');
        $this->grantRole($user, $this->otherTenant, 'teacher');
        $this->actingInPanel($this->manager, $this->tenant);

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('removeFromTenant')->table($user));

        $this->assertSame([], $this->roleNamesIn($user, $this->tenant));
        $this->assertSame(['teacher'], $this->roleNamesIn($user, $this->otherTenant));
    }

    public function test_an_existing_user_is_added_to_the_tenant_without_a_new_account(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, $this->otherTenant, 'teacher');
        $this->actingInPanel($this->manager, $this->tenant);
        $teacher = Role::findByName('teacher', 'web');

        Livewire::test(ListUsers::class)
            ->callAction('addExistingUser', ['user_id' => $user->id, 'roles' => [$teacher->id]])
            ->assertHasNoFormErrors();

        $this->assertSame(['teacher'], $this->roleNamesIn($user, $this->tenant));
        $this->assertSame(['teacher'], $this->roleNamesIn($user, $this->otherTenant));
        $this->assertSame(1, User::where('email', $user->email)->count());
    }

    /**
     * @return list<string>
     */
    private function roleNamesIn(User $user, Tenant $tenant): array
    {
        return $tenant->execute(fn () => $user->unsetRelation('roles')->roles()->pluck('name')->sort()->values()->all());
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Auth\Impersonation;
use App\Auth\RecentCodeLogin;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->tenant = Tenant::factory()->create();

        $this->roleWithPermissions('manager', ['ViewAny:User', 'View:User', 'Update:User']);
        $this->roleWithPermissions('teacher', []);

        $this->teacher = User::factory()->create();
        $this->grantRole($this->teacher, $this->tenant, 'teacher');
    }

    public function test_a_super_admin_logs_in_as_another_user_from_the_edit_page(): void
    {
        $this->actingInPanel($this->admin, $this->tenant);
        session([ResolveCurrentTenant::SESSION_KEY => $this->tenant->getKey(), RecentCodeLogin::SESSION_KEY => now()->getTimestamp()]);

        Livewire::test(EditUser::class, ['record' => $this->teacher->getRouteKey()])
            ->callAction(TestAction::make('impersonate'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->teacher);
        $this->assertSame($this->admin->getKey(), session(Impersonation::SESSION_KEY));
        // The admin's tenant choice and password-reset window do not carry over.
        $this->assertNull(session(ResolveCurrentTenant::SESSION_KEY));
        $this->assertNull(session(RecentCodeLogin::SESSION_KEY));
        // auth.session compares this with the logged-in user's password.
        $this->assertSame(Auth::guard('web')->hashPasswordForCookie($this->teacher->password), session('password_hash_web'));
    }

    public function test_staff_are_sent_to_the_panel_root(): void
    {
        $this->makeStaff($this->teacher, $this->tenant);
        $this->actingInPanel($this->admin, $this->tenant);

        Livewire::test(EditUser::class, ['record' => $this->teacher->getRouteKey()])
            ->callAction(TestAction::make('impersonate'))
            ->assertRedirect(url('cpadmin'));
    }

    public function test_the_user_panel_shows_the_way_back(): void
    {
        $this->actingAs($this->admin);
        Impersonation::start($this->admin, $this->teacher);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.id', $this->teacher->id)
                ->where('auth.impersonator.id', $this->admin->id));
        $this->assertAuthenticatedAs($this->teacher);
    }

    public function test_the_panel_shows_the_way_back(): void
    {
        $this->makeStaff($this->teacher, $this->tenant);
        $this->actingAs($this->admin);
        Impersonation::start($this->admin, $this->teacher);

        $this->get("/cpadmin/{$this->tenant->slug}")
            ->assertOk()
            ->assertSee('بازگشت به حساب خودم')
            ->assertSee(route('impersonation.leave'));
    }

    public function test_leaving_logs_the_super_admin_back_in_where_they_started(): void
    {
        $this->actingAs($this->admin);
        Impersonation::start($this->admin, $this->teacher, '/cpadmin/head-office/users/'.$this->teacher->id.'/edit');

        $this->post(route('impersonation.leave'))
            ->assertRedirect('/cpadmin/head-office/users/'.$this->teacher->id.'/edit');

        $this->assertAuthenticatedAs($this->admin);
        $this->assertFalse(session()->has(Impersonation::SESSION_KEY));
        $this->assertSame(Auth::guard('web')->hashPasswordForCookie($this->admin->password), session('password_hash_web'));

        $this->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_leaving_without_an_impersonation_is_not_found(): void
    {
        $this->actingAs($this->teacher)->post(route('impersonation.leave'))->assertNotFound();

        $this->assertAuthenticatedAs($this->teacher);
    }

    public function test_only_super_admins_may_impersonate(): void
    {
        $manager = User::factory()->create();
        $this->makeStaff($manager, $this->tenant);
        $this->grantRole($manager, $this->tenant, 'manager');
        $this->actingInPanel($manager, $this->tenant);

        Livewire::test(EditUser::class, ['record' => $this->teacher->getRouteKey()])
            ->assertActionHidden(TestAction::make('impersonate'));

        $this->expectExceptionObject(new HttpException(403));
        Impersonation::start($manager, $this->teacher);
    }

    public function test_a_super_admin_cannot_impersonate_another_super_admin(): void
    {
        $otherAdmin = User::factory()->superAdmin()->create();

        $this->assertFalse(Impersonation::canImpersonate($this->admin, $otherAdmin));
        $this->assertFalse(Impersonation::canImpersonate($this->admin, $this->admin));
        $this->assertTrue(Impersonation::canImpersonate($this->admin, $this->teacher));
    }
}

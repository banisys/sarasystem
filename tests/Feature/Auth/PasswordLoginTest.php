<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PasswordLoginTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'teacher@example.com',
            'mobile' => '09121234567',
            'national_code' => '0084575948',
        ]);
        $this->grantRole($this->user, Tenant::factory()->create(), 'teacher');
    }

    public function test_the_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function identifiers(): array
    {
        return [
            'mobile' => ['09121234567'],
            'mobile in persian digits with +98' => ['+۹۸۹۱۲۱۲۳۴۵۶۷'],
            'national code' => ['0084575948'],
            'email in any case' => ['Teacher@Example.com'],
        ];
    }

    #[DataProvider('identifiers')]
    public function test_users_log_in_with_any_identifier(string $identifier): void
    {
        $this->post('/login', ['identifier' => $identifier, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->post('/login', ['identifier' => '0084575948', 'password' => 'wrong'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_a_shared_mobile_asks_for_the_national_code(): void
    {
        User::factory()->create(['mobile' => '09121234567']);

        $this->post('/login', ['identifier' => '09121234567', 'password' => 'password'])
            ->assertSessionHasErrors(['identifier' => 'این شماره موبایل برای چند حساب ثبت شده است؛ با کد ملی یا ایمیل وارد شوید.']);

        $this->assertGuest();
    }

    public function test_too_many_failures_lock_the_identifier_out(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['identifier' => '0084575948', 'password' => 'wrong']);
        }

        $this->post('/login', ['identifier' => '0084575948', 'password' => 'password'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_login_and_back_to_the_admin_panel(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeStaff($this->user, $tenant);

        $this->get("/cpadmin/{$tenant->slug}")->assertRedirect('/login');

        $this->post('/login', ['identifier' => '0084575948', 'password' => 'password'])
            ->assertRedirect("/cpadmin/{$tenant->slug}");
    }

    public function test_a_user_with_several_tenants_picks_one_after_login(): void
    {
        $second = Tenant::factory()->create();
        $this->grantRole($this->user, $second, 'teacher');

        $this->post('/login', ['identifier' => '0084575948', 'password' => 'password']);

        $this->get('/dashboard')->assertRedirect(route('tenants.select'));

        $this->post(route('tenants.switch', $second->slug))->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard')->where('tenant.current.id', $second->id));
    }

    public function test_logged_in_users_skip_the_login_page_and_can_log_out(): void
    {
        $this->actingAs($this->user)->get('/login')->assertRedirect(route('dashboard'));

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}

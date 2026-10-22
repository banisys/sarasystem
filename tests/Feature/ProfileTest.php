<?php

namespace Tests\Feature;

use App\Auth\RecentCodeLogin;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private FakeSmsSender $sms;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->user = User::factory()->create(['mobile' => '09121234567', 'national_code' => '0084575948']);
    }

    public function test_the_profile_page_asks_for_the_current_password_after_a_password_login(): void
    {
        $this->actingAs($this->user)->get('/profile')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->where('profile.mobile', '09121234567')
                ->where('currentPasswordRequired', true));
    }

    public function test_right_after_a_code_login_no_current_password_is_needed(): void
    {
        $this->actingAs($this->user)
            ->withSession([RecentCodeLogin::SESSION_KEY => now()->getTimestamp()])
            ->get('/profile')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('currentPasswordRequired', false));

        $this->put('/profile/password', ['password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-secret-1', $this->user->fresh()->password));
        $this->assertNull(session(RecentCodeLogin::SESSION_KEY), 'The free pass is used up.');
    }

    public function test_the_code_login_free_pass_expires(): void
    {
        $this->actingAs($this->user)->withSession([RecentCodeLogin::SESSION_KEY => now()->getTimestamp()]);

        $this->travel(RecentCodeLogin::VALID_SECONDS + 1)->seconds();

        $this->put('/profile/password', ['password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasErrors('current_password');
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $this->actingAs($this->user)
            ->put('/profile/password', ['current_password' => 'wrong', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasErrors('current_password');

        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-secret-1', $this->user->fresh()->password));
    }

    public function test_a_session_with_the_old_password_is_logged_out(): void
    {
        $oldHash = $this->user->password;
        $this->user->forceFill(['password' => 'changed-elsewhere'])->save();

        $this->actingAs($this->user)
            ->withSession(['password_hash_web' => $oldHash])
            ->get('/profile')
            ->assertRedirect('/login');
    }

    public function test_email_is_saved_and_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->user)->put('/profile/email', ['email' => 'Me@Example.com'])->assertSessionHasNoErrors();
        $this->assertSame('me@example.com', $this->user->fresh()->email);

        $this->put('/profile/email', ['email' => 'taken@example.com'])->assertSessionHasErrors('email');
    }

    public function test_email_cannot_be_removed_when_it_is_the_only_identifier(): void
    {
        $emailOnly = User::factory()->create(['email' => 'only@example.com', 'mobile' => null, 'national_code' => null]);

        $this->actingAs($emailOnly)->put('/profile/email', ['email' => ''])->assertSessionHasErrors('email');
        $this->assertSame('only@example.com', $emailOnly->fresh()->email);
    }

    public function test_a_new_mobile_is_saved_only_after_its_code_is_confirmed(): void
    {
        $this->actingAs($this->user)->post('/profile/mobile/code', ['mobile' => '۰۹۳۵۱۱۱۲۲۳۳'])->assertSessionHasNoErrors();

        $code = $this->sms->lastCodeFor('09351112233');
        $this->assertNotNull($code, 'The code goes to the new number.');
        $this->assertSame('09121234567', $this->user->fresh()->mobile, 'Nothing changes before confirmation.');

        $this->put('/profile/mobile', ['mobile' => '09351112233', 'code' => $code])->assertSessionHasNoErrors();

        $this->assertSame('09351112233', $this->user->fresh()->mobile);
        $this->assertNotNull($this->user->fresh()->mobile_verified_at);
    }

    public function test_a_code_for_one_number_does_not_confirm_another(): void
    {
        $this->actingAs($this->user)->post('/profile/mobile/code', ['mobile' => '09351112233']);
        $code = $this->sms->lastCodeFor('09351112233');

        $this->put('/profile/mobile', ['mobile' => '09359998877', 'code' => $code])->assertSessionHasErrors('code');
        $this->assertSame('09121234567', $this->user->fresh()->mobile);
    }

    public function test_the_current_mobile_is_not_sent_a_confirmation(): void
    {
        $this->actingAs($this->user)->post('/profile/mobile/code', ['mobile' => '09121234567'])->assertSessionHasErrors('mobile');
        $this->assertSame([], $this->sms->sent);
    }

    public function test_staff_find_the_account_page_in_the_admin_user_menu(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeStaff($this->user, $tenant);

        $this->actingAs($this->user)->get("/cpadmin/{$tenant->slug}")->assertOk()->assertSee(route('profile.edit'));
    }
}

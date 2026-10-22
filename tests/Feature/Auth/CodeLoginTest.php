<?php

namespace Tests\Feature\Auth;

use App\Auth\OneTimePassword;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class CodeLoginTest extends TestCase
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
        $this->grantRole($this->user, Tenant::factory()->create(), 'student');
    }

    public function test_a_code_is_sent_by_sms_and_logs_the_user_in(): void
    {
        $this->post('/login/code/send', ['identifier' => '0084575948'])->assertSessionHas('status');

        $code = $this->sms->lastCodeFor('09121234567');
        $this->assertNotNull($code);

        $this->post('/login/code', ['identifier' => '0084575948', 'code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->user);
        $this->assertNotNull($this->user->fresh()->mobile_verified_at);
    }

    public function test_a_code_works_only_once(): void
    {
        $this->post('/login/code/send', ['identifier' => '09121234567']);
        $code = $this->sms->lastCodeFor('09121234567');

        $this->post('/login/code', ['identifier' => '09121234567', 'code' => $code]);
        $this->post('/logout');

        $this->post('/login/code', ['identifier' => '09121234567', 'code' => $code])->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $this->post('/login/code/send', ['identifier' => '09121234567']);
        $code = $this->sms->lastCodeFor('09121234567');

        $this->travel(OneTimePassword::TTL_SECONDS + 1)->seconds();

        $this->post('/login/code', ['identifier' => '09121234567', 'code' => $code])->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_too_many_wrong_codes_throw_the_code_away(): void
    {
        $this->post('/login/code/send', ['identifier' => '09121234567']);
        $code = $this->sms->lastCodeFor('09121234567');
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, OneTimePassword::MAX_ATTEMPTS) as $attempt) {
            $this->post('/login/code', ['identifier' => '0084575948', 'code' => $wrong]);
        }

        // Use another identifier for the same user so the request lockout doesn't hide the result.
        $this->post('/login/code', ['identifier' => '09121234567', 'code' => $code])->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_a_new_code_must_wait_for_the_resend_delay(): void
    {
        $this->post('/login/code/send', ['identifier' => '09121234567']);

        $this->post('/login/code/send', ['identifier' => '09121234567'])->assertSessionHasErrors('identifier');
        $this->assertCount(1, $this->sms->sent);

        $this->travel(OneTimePassword::RESEND_AFTER_SECONDS)->seconds();

        $this->post('/login/code/send', ['identifier' => '09121234567'])->assertSessionHasNoErrors();
        $this->assertCount(2, $this->sms->sent);
    }

    public function test_an_unknown_identifier_gets_the_same_answer_and_no_sms(): void
    {
        $this->post('/login/code/send', ['identifier' => '09350000000'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame([], $this->sms->sent);
    }

    public function test_an_account_without_a_mobile_cannot_get_a_code(): void
    {
        User::factory()->create(['email' => 'no.mobile@example.com', 'mobile' => null]);

        $this->post('/login/code/send', ['identifier' => 'no.mobile@example.com'])->assertSessionHasErrors('identifier');
        $this->assertSame([], $this->sms->sent);
    }
}

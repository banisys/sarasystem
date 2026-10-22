<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared by the login requests: rate limiting per identifier + IP, and
 * resolving the identifier (mobile / national code / email) to one user.
 */
trait ResolvesLoginUser
{
    public const MAX_ATTEMPTS = 5;

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => "تعداد تلاش‌ها زیاد بود. {$seconds} ثانیه‌ی دیگر دوباره امتحان کنید.",
        ]);
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey());
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * The single user behind the identifier, or null when there is none.
     * A mobile shared by several accounts is an error: the user is asked to
     * use their national code instead (ADR 0004).
     */
    public function loginUser(): ?User
    {
        $users = User::query()->whereLoginIdentifier($this->string('identifier'))->limit(2)->get();

        if ($users->count() > 1) {
            throw ValidationException::withMessages([
                'identifier' => 'این شماره موبایل برای چند حساب ثبت شده است؛ با کد ملی یا ایمیل وارد شوید.',
            ]);
        }

        return $users->first();
    }

    public function failLogin(string $message = 'اطلاعات ورود درست نیست.'): never
    {
        $this->hitRateLimiter();

        throw ValidationException::withMessages(['identifier' => $message]);
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower($this->string('identifier'))).'|'.$this->ip();
    }
}

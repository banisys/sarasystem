<?php

namespace App\Auth;

/**
 * A mobile confirmed by an SMS code on a public form whose sender has no
 * account, e.g. «همکاری با ما» (ADR 0014) or student preregistration
 * (ADR 0018). The code is a guest code, and the confirmed number is kept in
 * the session for a while so the rest of the form can be filled at leisure.
 */
abstract class GuestMobile
{
    public const OTP_OWNER = 'guest';

    public const VERIFIED_FOR_MINUTES = 60;

    public function __construct(private OneTimePassword $otp) {}

    /**
     * The OTP purpose; also keeps each form's confirmed number apart.
     */
    abstract protected function purpose(): string;

    public function secondsUntilResend(string $mobile): int
    {
        return $this->otp->secondsUntilResend(self::OTP_OWNER, $this->purpose(), $mobile);
    }

    public function sendCode(string $mobile): void
    {
        $this->otp->send(self::OTP_OWNER, $this->purpose(), $mobile);
    }

    /**
     * Checks the code and, when right, remembers the mobile as confirmed.
     */
    public function verify(string $mobile, string $code): bool
    {
        if (! $this->otp->verify(self::OTP_OWNER, $code, $this->purpose(), $mobile)) {
            return false;
        }

        session()->put($this->sessionKey(), ['mobile' => $mobile, 'until' => now()->addMinutes(self::VERIFIED_FOR_MINUTES)->getTimestamp()]);

        return true;
    }

    /**
     * The mobile confirmed in this session, if still valid.
     */
    public function verified(): ?string
    {
        $entry = session()->get($this->sessionKey());

        return $entry !== null && $entry['until'] > now()->getTimestamp() ? $entry['mobile'] : null;
    }

    public function forget(): void
    {
        session()->forget($this->sessionKey());
    }

    /**
     * 0912***4567, to hint which number a form was sent with.
     */
    public static function mask(string $mobile): string
    {
        return substr($mobile, 0, 4).'***'.substr($mobile, -4);
    }

    private function sessionKey(): string
    {
        return 'guest_mobile.'.$this->purpose();
    }
}

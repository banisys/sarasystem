<?php

namespace Modules\Employee\Support;

use App\Auth\OneTimePassword;

/**
 * The applicant's mobile, confirmed by an SMS code before the «همکاری با ما»
 * form is saved (ADR 0014). Applicants have no account, so the code is a
 * guest code, and the confirmed number is kept in the session for a while
 * so the rest of the form can be filled at leisure.
 */
class ApplicantMobile
{
    public const OTP_OWNER = 'guest';

    public const OTP_PURPOSE = 'job-application';

    public const VERIFIED_FOR_MINUTES = 60;

    private const SESSION_KEY = 'employee.careers.verified_mobile';

    public function __construct(private OneTimePassword $otp) {}

    public function secondsUntilResend(string $mobile): int
    {
        return $this->otp->secondsUntilResend(self::OTP_OWNER, self::OTP_PURPOSE, $mobile);
    }

    public function sendCode(string $mobile): void
    {
        $this->otp->send(self::OTP_OWNER, self::OTP_PURPOSE, $mobile);
    }

    /**
     * Checks the code and, when right, remembers the mobile as confirmed.
     */
    public function verify(string $mobile, string $code): bool
    {
        if (! $this->otp->verify(self::OTP_OWNER, $code, self::OTP_PURPOSE, $mobile)) {
            return false;
        }

        session()->put(self::SESSION_KEY, ['mobile' => $mobile, 'until' => now()->addMinutes(self::VERIFIED_FOR_MINUTES)->getTimestamp()]);

        return true;
    }

    /**
     * The mobile confirmed in this session, if still valid.
     */
    public function verified(): ?string
    {
        $entry = session()->get(self::SESSION_KEY);

        return $entry !== null && $entry['until'] > now()->getTimestamp() ? $entry['mobile'] : null;
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * 0912***4567, to hint which number an application was sent with.
     */
    public static function mask(string $mobile): string
    {
        return substr($mobile, 0, 4).'***'.substr($mobile, -4);
    }
}

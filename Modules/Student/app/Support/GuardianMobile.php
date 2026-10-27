<?php

namespace Modules\Student\Support;

use App\Auth\GuestMobile;

/**
 * The family's mobile, confirmed by an SMS code before the «پیش‌ثبت‌نام»
 * form is saved (ADR 0018).
 */
class GuardianMobile extends GuestMobile
{
    public const OTP_PURPOSE = 'student-preregistration';

    protected function purpose(): string
    {
        return self::OTP_PURPOSE;
    }
}

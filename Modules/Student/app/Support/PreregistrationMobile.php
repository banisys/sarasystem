<?php

namespace Modules\Student\Support;

use App\Auth\GuestMobile;

/**
 * The applicant's mobile (a parent's, for a child), confirmed by an SMS
 * code before the «پیش‌ثبت‌نام» form is saved (ADR 0018).
 */
class PreregistrationMobile extends GuestMobile
{
    public const OTP_PURPOSE = 'student-preregistration';

    protected function purpose(): string
    {
        return self::OTP_PURPOSE;
    }
}

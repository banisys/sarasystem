<?php

namespace Modules\Employee\Support;

use App\Auth\GuestMobile;

/**
 * The applicant's mobile, confirmed by an SMS code before the «همکاری با ما»
 * form is saved (ADR 0014).
 */
class ApplicantMobile extends GuestMobile
{
    public const OTP_PURPOSE = 'job-application';

    protected function purpose(): string
    {
        return self::OTP_PURPOSE;
    }
}

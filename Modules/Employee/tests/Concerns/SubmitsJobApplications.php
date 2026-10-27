<?php

namespace Modules\Employee\Tests\Concerns;

use Modules\Employee\Database\Factories\JobApplicationFactory;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Http\Controllers\CareersController;
use Modules\Employee\Tests\Feature\CareersFormTest;

/**
 * Sending the «همکاری با ما» form in tests. Needs $this->sms (FakeSmsSender)
 * and $this->position (JobPosition).
 */
trait SubmitsJobApplications
{
    /**
     * Confirm the mobile by SMS in this session, as the form does.
     */
    protected function verifyMobile(string $mobile = '09121234567'): void
    {
        $this->travel(2)->minutes();
        $this->post('/careers/mobile/code', ['mobile' => $mobile])->assertSessionHasNoErrors();
        $this->post('/careers/mobile/verify', ['mobile' => $mobile, 'code' => $this->sms->lastCodeFor($mobile)])->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validData(array $overrides = []): array
    {
        return [
            'job_position_id' => $this->position->id,
            'first_name' => 'زهرا',
            'last_name' => 'احمدی',
            'father_name' => 'علی',
            'nationality' => 'iranian',
            'national_code' => JobApplicationFactory::nationalCode(),
            'birth_date' => '1369/01/05',
            'gender' => Gender::Female->value,
            'military_status' => '',
            'mobile' => '09121234567',
            'phone' => '02112345678',
            'email' => 'zahra@example.com',
            'postal_code' => '1234567890',
            'address' => 'تهران، خیابان آزادی، پلاک ۱۰',
            'education' => [[
                'level' => 'bachelor', 'major' => 'ریاضی', 'orientation' => 'محض', 'average' => '17.25', 'rank' => '',
                'graduation_date' => '1394/04/31', 'university_type' => 'state', 'university' => 'دانشگاه تهران',
            ]],
            'work_history' => [[
                'company_name' => 'مدرسه نمونه', 'job_position' => 'معلم', 'start_date' => '1395/07/01', 'end_date' => '',
                'termination_reason' => '', 'company_phone' => '',
            ]],
            'resume' => CareersFormTest::pdf(),
            CareersController::HONEYPOT => '',
            ...$overrides,
        ];
    }
}

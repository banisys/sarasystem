<?php

namespace Modules\Student\Tests\Concerns;

use Modules\Student\Database\Factories\PreregistrationFactory;
use Modules\Student\Enums\Gender;
use Modules\Student\Http\Controllers\PreregistrationController;

/**
 * Sending the «پیش‌ثبت‌نام» form in tests. Needs $this->sms (FakeSmsSender),
 * $this->stage and $this->grade (Grade).
 */
trait SubmitsPreregistrations
{
    /**
     * Confirm the mobile by SMS in this session, as the form does.
     */
    protected function verifyMobile(string $mobile = '09121234567'): void
    {
        $this->travel(2)->minutes();
        $this->post('/preregister/mobile/code', ['mobile' => $mobile])->assertSessionHasNoErrors();
        $this->post('/preregister/mobile/verify', ['mobile' => $mobile, 'code' => $this->sms->lastCodeFor($mobile)])->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validData(array $overrides = []): array
    {
        return [
            'stage_id' => $this->stage->id,
            'grade_id' => $this->grade->id,
            'preferred_tenant_id' => '',
            'first_name' => 'امیر',
            'last_name' => 'محمدی',
            'father_name' => 'علی',
            'nationality' => 'iranian',
            'national_code' => PreregistrationFactory::nationalCode(),
            'birth_certificate_number' => '123456',
            'birth_date' => '1393/02/10',
            'birth_place' => 'تهران',
            'gender' => Gender::Male->value,
            'mobile' => '09121234567',
            'phone' => '02112345678',
            'email' => '',
            'postal_code' => '',
            'city' => 'تهران',
            'address' => 'تهران، خیابان آزادی، پلاک ۱۰',
            'previous_school' => 'دبستان نمونه',
            'last_average' => '19.5',
            PreregistrationController::HONEYPOT => '',
            ...$overrides,
        ];
    }
}

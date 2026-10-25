<?php

namespace Modules\Employee\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Ariaieboy\Jalali\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\Employee\Database\Factories\JobApplicationFactory;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Enums\MilitaryStatus;
use Modules\Employee\Http\Controllers\CareersController;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class CareersFormTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private JobPosition $position;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create(['name' => 'ستاد', 'slug' => 'head-office']);
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['slug' => 'west']);
        $this->position = JobPosition::factory()->for($this->primary)->create(['title' => 'معلم ریاضی']);
    }

    public function test_the_form_lists_the_primary_tenants_active_positions(): void
    {
        JobPosition::factory()->for($this->primary)->inactive()->create(['title' => 'غیرفعال']);
        JobPosition::factory()->for($this->west)->create(['title' => 'شغل شعبه']);

        $this->get('/careers')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Employee::Careers')
                ->where('site.tenant.name', 'ستاد')
                ->has('positions', 1)
                ->where('positions.0.title', 'معلم ریاضی'));
    }

    public function test_the_form_is_only_on_the_primary_tenants_site(): void
    {
        $this->get('/west/careers')->assertNotFound();

        $this->primary->forceFill(['is_primary' => false])->save();

        $this->get('/careers')->assertNotFound();
        $this->post('/careers', $this->validData())->assertNotFound();
    }

    public function test_an_application_is_filed_with_the_primary_tenant_without_creating_a_user(): void
    {
        $this->verifyMobile();
        $this->post('/careers', $this->validData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('careers.create'))
            ->assertSessionHas('status', CareersController::SUBMITTED_MESSAGE);

        $application = JobApplication::withoutTenantScope()->sole();

        $this->assertSame($this->primary->id, $application->tenant_id);
        $this->assertSame($this->position->id, $application->job_position_id);
        $this->assertSame('معلم ریاضی', $application->job_title);
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertNull($application->closed_at);
        $this->assertSame('1990-03-25', $application->birth_date->toDateString());
        $this->assertSame('2015-07-22', $application->education[0]['graduation_date']);
        $this->assertSame('2016-09-22', $application->work_history[0]['start_date']);
        $this->assertNull($application->work_history[0]['end_date']);
        $this->assertSame(0, User::count());

        $resume = $application->getFirstMedia(JobApplication::RESUME_COLLECTION);
        $this->assertSame('local', $resume->disk);
        Storage::disk('local')->assertExists($resume->getPathRelativeToRoot());
    }

    public function test_persian_digits_and_mobile_formats_are_normalized(): void
    {
        $this->verifyMobile();
        $code = JobApplicationFactory::nationalCode();
        $persian = strtr($code, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        $this->post('/careers', $this->validData(['national_code' => $persian, 'mobile' => '+98 912 123 4567', 'birth_date' => '۱۳۶۹/۰۱/۰۵']))
            ->assertSessionHasNoErrors();

        $application = JobApplication::withoutTenantScope()->sole();
        $this->assertSame($code, $application->national_code);
        $this->assertSame('09121234567', $application->mobile);
    }

    public function test_applying_again_updates_the_open_application_and_replaces_the_resume(): void
    {
        $this->verifyMobile();
        $this->post('/careers', $this->validData(['national_code' => '0013542419']))->assertSessionHasNoErrors();
        $firstResume = JobApplication::withoutTenantScope()->sole()->getFirstMedia(JobApplication::RESUME_COLLECTION);

        $this->verifyMobile();
        $this->post('/careers', $this->validData(['national_code' => '0013542419', 'first_name' => 'مریم']))->assertSessionHasNoErrors();

        $application = JobApplication::withoutTenantScope()->sole();
        $this->assertSame('مریم', $application->first_name);
        $this->assertCount(1, $application->getMedia(JobApplication::RESUME_COLLECTION));
        Storage::disk('local')->assertMissing($firstResume->getPathRelativeToRoot());
    }

    public function test_a_national_code_has_one_open_application_across_all_tenants(): void
    {
        $this->verifyMobile();
        $existing = JobApplication::factory()->for($this->west)->create(['national_code' => '0013542419', 'mobile' => '09121234567']);

        $this->post('/careers', $this->validData(['national_code' => '0013542419', 'first_name' => 'مریم']))->assertSessionHasNoErrors();

        $this->assertSame(1, JobApplication::withoutTenantScope()->count());
        $this->assertSame('مریم', $existing->fresh()->first_name);
    }

    public function test_a_closed_application_does_not_stop_a_new_one(): void
    {
        $this->verifyMobile();
        $closed = JobApplication::factory()->for($this->primary)->closed()->create(['national_code' => '0013542419']);
        $this->assertNotNull($closed->closed_at);

        $this->post('/careers', $this->validData(['national_code' => '0013542419']))->assertSessionHasNoErrors();

        $this->assertSame(2, JobApplication::withoutTenantScope()->count());
        $this->assertSame(1, JobApplication::withoutTenantScope()->open()->count());
    }

    public function test_military_status_is_asked_of_iranian_men_only(): void
    {
        $this->verifyMobile();
        $this->post('/careers', $this->validData(['gender' => Gender::Male->value, 'military_status' => '']))
            ->assertSessionHasErrors('military_status');

        $this->post('/careers', $this->validData(['gender' => Gender::Female->value, 'military_status' => MilitaryStatus::Liable->value]))
            ->assertSessionHasNoErrors();

        $this->assertNull(JobApplication::withoutTenantScope()->sole()->military_status);
    }

    public function test_a_foreign_applicant_gives_a_twelve_digit_code(): void
    {
        $this->verifyMobile();
        $this->post('/careers', $this->validData(['nationality' => 'foreign', 'national_code' => '0013542419']))
            ->assertSessionHasErrors('national_code');

        $this->post('/careers', $this->validData(['nationality' => 'foreign', 'national_code' => '123456789012', 'gender' => Gender::Male->value, 'military_status' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_the_university_is_asked_for_university_degrees_only(): void
    {
        $this->verifyMobile();
        $diploma = ['level' => 'diploma', 'major' => 'ریاضی', 'average' => '18', 'graduation_date' => '1388/04/01'];

        $this->post('/careers', $this->validData(['education' => [[...$diploma, 'level' => 'bachelor']]]))
            ->assertSessionHasErrors(['education.0.university', 'education.0.university_type']);

        $this->post('/careers', $this->validData(['education' => [$diploma]]))
            ->assertSessionHasNoErrors();
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->verifyMobile();
        $inactive = JobPosition::factory()->for($this->primary)->inactive()->create();
        $foreign = JobPosition::factory()->for($this->west)->create();
        $currentYear = Jalali::now()->getYear();

        $this->post('/careers', $this->validData(['job_position_id' => $inactive->id]))->assertSessionHasErrors('job_position_id');
        $this->post('/careers', $this->validData(['job_position_id' => $foreign->id]))->assertSessionHasErrors('job_position_id');
        $this->post('/careers', $this->validData(['national_code' => '1234567890']))->assertSessionHasErrors('national_code');
        $this->post('/careers', $this->validData(['birth_date' => ($currentYear - 10).'/01/01']))->assertSessionHasErrors('birth_date');
        $this->post('/careers', $this->validData(['birth_date' => '1370/12/31']))->assertSessionHasErrors('birth_date');
        $this->post('/careers', $this->validData(['education' => []]))->assertSessionHasErrors('education');
        $this->post('/careers', $this->validData(['resume' => UploadedFile::fake()->create('cv.docx', 10)]))->assertSessionHasErrors('resume');
        $this->post('/careers', $this->validData(['work_history' => [[
            'company_name' => 'مدرسه', 'job_position' => 'معلم', 'start_date' => '1400/01/01', 'end_date' => '1399/01/01',
        ]]]))->assertSessionHasErrors('work_history.0.end_date');

        $this->assertSame(0, JobApplication::withoutTenantScope()->count());
    }

    public function test_work_history_and_home_phone_are_optional(): void
    {
        $this->verifyMobile();
        $this->post('/careers', $this->validData(['work_history' => [], 'phone' => '']))->assertSessionHasNoErrors();

        $this->assertSame([], JobApplication::withoutTenantScope()->sole()->work_history);
    }

    public function test_the_form_is_saved_only_with_a_mobile_confirmed_by_sms(): void
    {
        $this->post('/careers', $this->validData())->assertSessionHasErrors('mobile');

        $this->verifyMobile('09351234567');
        $this->post('/careers', $this->validData())->assertSessionHasErrors('mobile');

        $this->assertSame(0, JobApplication::withoutTenantScope()->count());
    }

    public function test_a_code_confirms_the_mobile_for_an_hour_and_is_used_up_by_saving(): void
    {
        $this->post('/careers/mobile/code', ['mobile' => '۰۹۱۲۱۲۳۴۵۶۷'])->assertSessionHasNoErrors();
        $this->assertSame('09121234567', $this->sms->sent[0]['mobile']);

        $this->post('/careers/mobile/verify', ['mobile' => '09121234567', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->get('/careers')->assertInertia(fn (AssertableInertia $page) => $page->where('mobileVerification.verifiedMobile', null));

        $this->post('/careers/mobile/verify', ['mobile' => '09121234567', 'code' => $this->sms->lastCodeFor('09121234567')])->assertSessionHasNoErrors();
        $this->get('/careers')->assertInertia(fn (AssertableInertia $page) => $page->where('mobileVerification.verifiedMobile', '09121234567'));

        $this->travel(61)->minutes();
        $this->get('/careers')->assertInertia(fn (AssertableInertia $page) => $page->where('mobileVerification.verifiedMobile', null));
        $this->post('/careers', $this->validData())->assertSessionHasErrors('mobile');

        $this->verifyMobile();
        $this->post('/careers', $this->validData())->assertSessionHasNoErrors();
        $this->post('/careers', $this->validData())->assertSessionHasErrors('mobile');
    }

    public function test_a_new_code_waits_for_the_resend_delay(): void
    {
        $this->post('/careers/mobile/code', ['mobile' => '09121234567'])->assertSessionHasNoErrors();
        $this->post('/careers/mobile/code', ['mobile' => '09121234567'])->assertSessionHasErrors('mobile');
        $this->post('/careers/mobile/code', ['mobile' => '12345'])->assertSessionHasErrors('mobile');

        $this->assertCount(1, $this->sms->sent);
    }

    public function test_an_open_application_can_only_be_updated_from_its_own_mobile(): void
    {
        $existing = JobApplication::factory()->for($this->primary)->create(['national_code' => '0013542419', 'mobile' => '09351112233']);

        $this->verifyMobile();
        $this->post('/careers', $this->validData(['national_code' => '0013542419', 'first_name' => 'مهاجم']))
            ->assertSessionHasErrors(['national_code' => 'برای این کد ملی درخواستی با شماره‌ی 0935***2233 ثبت شده است. برای ویرایش آن با همان شماره اقدام کنید.']);

        $this->assertNotSame('مهاجم', $existing->fresh()->first_name);
    }

    public function test_sending_and_checking_codes_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/careers/mobile/verify', ['mobile' => '09121234567', 'code' => '000000']);
        }

        $this->post('/careers/mobile/code', ['mobile' => '09121234567'])->assertTooManyRequests();
        $this->assertCount(0, $this->sms->sent);
    }

    public function test_a_filled_honeypot_is_answered_like_a_success_but_not_saved(): void
    {
        $this->post('/careers', $this->validData([CareersController::HONEYPOT => 'https://spam.example']))
            ->assertRedirect(route('careers.create'))
            ->assertSessionHas('status', CareersController::SUBMITTED_MESSAGE);

        $this->assertSame(0, JobApplication::withoutTenantScope()->count());
    }

    public function test_submissions_are_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/careers', [CareersController::HONEYPOT => 'x'])->assertRedirect();
        }

        $this->post('/careers', [CareersController::HONEYPOT => 'x'])->assertTooManyRequests();
    }

    /**
     * Confirm the mobile by SMS in this session, as the form does.
     */
    private function verifyMobile(string $mobile = '09121234567'): void
    {
        $this->travel(2)->minutes();
        $this->post('/careers/mobile/code', ['mobile' => $mobile])->assertSessionHasNoErrors();
        $this->post('/careers/mobile/verify', ['mobile' => $mobile, 'code' => $this->sms->lastCodeFor($mobile)])->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
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
            'resume' => self::pdf(),
            CareersController::HONEYPOT => '',
            ...$overrides,
        ];
    }

    public static function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }
}

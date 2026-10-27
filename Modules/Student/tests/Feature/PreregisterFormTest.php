<?php

namespace Modules\Student\Tests\Feature;

use App\Models\FormCustomization;
use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Ariaieboy\Jalali\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Http\Controllers\PreregistrationController;
use Modules\Student\Http\Requests\StorePreregistrationRequest;
use Modules\Student\Models\Grade;
use Modules\Student\Models\Preregistration;
use Modules\Student\Tests\Concerns\SubmitsPreregistrations;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class PreregisterFormTest extends TestCase
{
    use RefreshDatabase, SubmitsPreregistrations;

    private Tenant $primary;

    private Tenant $west;

    private Grade $stage;

    private Grade $grade;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create(['name' => 'ستاد', 'slug' => 'head-office']);
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'غرب', 'slug' => 'west']);
        $this->stage = Grade::factory()->for($this->primary)->create(['title' => 'متوسطه اول']);
        $this->grade = Grade::factory()->childOf($this->stage)->create(['title' => 'هفتم']);
    }

    public function test_the_form_lists_the_primary_tenants_active_stages_and_grades(): void
    {
        Grade::factory()->childOf($this->stage)->inactive()->create(['title' => 'غیرفعال']);
        Grade::factory()->for($this->primary)->inactive()->create(['title' => 'مقطع غیرفعال']);
        Grade::factory()->for($this->west)->create(['title' => 'مقطع شعبه']);

        $this->get('/preregister')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Student::Preregister')
                ->where('site.tenant.name', 'ستاد')
                ->has('stages', 1)
                ->where('stages.0.title', 'متوسطه اول')
                ->has('stages.0.grades', 1)
                ->where('stages.0.grades.0.title', 'هفتم')
                // The head office is not a school to choose.
                ->has('options.tenants', 1)
                ->where('options.tenants.0.name', 'غرب'));
    }

    public function test_the_form_is_only_on_the_primary_tenants_site(): void
    {
        $this->get('/west/preregister')->assertNotFound();

        $this->primary->forceFill(['is_primary' => false])->save();

        $this->get('/preregister')->assertNotFound();
        $this->post('/preregister', $this->validData())->assertNotFound();
    }

    public function test_a_preregistration_is_filed_with_the_primary_tenant_without_creating_a_user(): void
    {
        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['preferred_tenant_id' => $this->west->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('preregister.create'))
            ->assertSessionHas('status', PreregistrationController::SUBMITTED_MESSAGE);

        $preregistration = Preregistration::withoutTenantScope()->sole();

        $this->assertSame($this->primary->id, $preregistration->tenant_id);
        $this->assertSame($this->grade->id, $preregistration->grade_id);
        $this->assertSame('متوسطه اول - هفتم', $preregistration->grade_title);
        $this->assertSame($this->west->id, $preregistration->preferred_tenant_id);
        $this->assertSame(PreregistrationStatus::Submitted, $preregistration->status);
        $this->assertSame('2014-04-30', $preregistration->birth_date->toDateString());
        $this->assertSame('19.50', $preregistration->last_average);
        $this->assertNull($preregistration->email);
        $this->assertSame(0, User::count());
    }

    public function test_a_stage_with_grades_needs_one_of_its_grades(): void
    {
        $otherStage = Grade::factory()->for($this->primary)->create(['title' => 'دبستان']);
        $otherGrade = Grade::factory()->childOf($otherStage)->create(['title' => 'اول']);

        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['grade_id' => '']))->assertSessionHasErrors('grade_id');
        $this->post('/preregister', $this->validData(['grade_id' => $otherGrade->id]))->assertSessionHasErrors('grade_id');

        $this->assertSame(0, Preregistration::withoutTenantScope()->count());
    }

    public function test_a_stage_without_grades_is_chosen_itself(): void
    {
        $level = Grade::factory()->for($this->primary)->create(['title' => 'Starter']);

        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['stage_id' => $level->id, 'grade_id' => '']))->assertSessionHasNoErrors();

        $preregistration = Preregistration::withoutTenantScope()->sole();
        $this->assertSame($level->id, $preregistration->grade_id);
        $this->assertSame('Starter', $preregistration->grade_title);
    }

    public function test_sending_again_updates_the_open_preregistration(): void
    {
        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['national_code' => '0013542419']))->assertSessionHasNoErrors();
        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['national_code' => '0013542419', 'first_name' => 'آرش']))->assertSessionHasNoErrors();

        $this->assertSame('آرش', Preregistration::withoutTenantScope()->sole()->first_name);
    }

    public function test_an_open_preregistration_can_only_be_updated_from_its_own_mobile(): void
    {
        $existing = Preregistration::factory()->for($this->primary)->create(['national_code' => '0013542419', 'mobile' => '09351112233']);

        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['national_code' => '0013542419', 'first_name' => 'مهاجم']))
            ->assertSessionHasErrors(['national_code' => 'برای این کد ملی پیش‌ثبت‌نامی با شماره‌ی 0935***2233 ثبت شده است. برای ویرایش آن با همان شماره اقدام کنید.']);

        $this->assertNotSame('مهاجم', $existing->fresh()->first_name);
    }

    public function test_a_closed_preregistration_does_not_stop_a_new_one(): void
    {
        Preregistration::factory()->for($this->primary)->closed()->create(['national_code' => '0013542419']);

        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['national_code' => '0013542419']))->assertSessionHasNoErrors();

        $this->assertSame(2, Preregistration::withoutTenantScope()->count());
        $this->assertSame(1, Preregistration::withoutTenantScope()->open()->count());
    }

    public function test_the_form_is_saved_only_with_a_mobile_confirmed_by_sms(): void
    {
        $this->post('/preregister', $this->validData())->assertSessionHasErrors('mobile');

        $this->verifyMobile('09351234567');
        $this->post('/preregister', $this->validData())->assertSessionHasErrors('mobile');

        $this->assertSame(0, Preregistration::withoutTenantScope()->count());
    }

    public function test_a_foreign_student_gives_a_twelve_digit_code(): void
    {
        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['nationality' => 'foreign', 'national_code' => '0013542419']))
            ->assertSessionHasErrors('national_code');

        $this->post('/preregister', $this->validData(['nationality' => 'foreign', 'national_code' => '۱۲۳۴۵۶۷۸۹۰۱۲']))
            ->assertSessionHasNoErrors();
        $this->assertSame('123456789012', Preregistration::withoutTenantScope()->sole()->national_code);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->verifyMobile();
        $currentYear = Jalali::now()->getYear();
        $inactive = Grade::factory()->for($this->primary)->inactive()->create();
        $foreign = Grade::factory()->for($this->west)->create();

        $this->post('/preregister', $this->validData(['stage_id' => $inactive->id, 'grade_id' => '']))->assertSessionHasErrors('stage_id');
        $this->post('/preregister', $this->validData(['stage_id' => $foreign->id, 'grade_id' => '']))->assertSessionHasErrors('stage_id');
        $this->post('/preregister', $this->validData(['stage_id' => $this->grade->id, 'grade_id' => '']))->assertSessionHasErrors('stage_id');
        $this->post('/preregister', $this->validData(['preferred_tenant_id' => $this->primary->id]))->assertSessionHasErrors('preferred_tenant_id');
        $this->post('/preregister', $this->validData(['national_code' => '1234567890']))->assertSessionHasErrors('national_code');
        $this->post('/preregister', $this->validData(['birth_date' => ($currentYear - 2).'/01/01']))->assertSessionHasErrors('birth_date');
        $this->post('/preregister', $this->validData(['birth_date' => ($currentYear - 25).'/01/01']))->assertSessionHasErrors('birth_date');

        $this->assertSame(0, Preregistration::withoutTenantScope()->count());
    }

    public function test_contact_and_school_fields_are_checked(): void
    {
        $this->verifyMobile();

        $this->post('/preregister', $this->validData(['phone' => '12345']))->assertSessionHasErrors('phone');
        $this->post('/preregister', $this->validData(['previous_school' => '']))->assertSessionHasErrors('previous_school');
        $this->post('/preregister', $this->validData(['last_average' => '21']))->assertSessionHasErrors('last_average');
        $this->post('/preregister', $this->validData(['address' => 'کوتاه']))->assertSessionHasErrors('address');

        $this->assertSame(0, Preregistration::withoutTenantScope()->count());
    }

    public function test_fields_follow_the_organizations_setup_of_the_stage(): void
    {
        FormCustomization::withoutTenantScope()->create([
            'tenant_id' => $this->primary->id,
            'form' => PreregistrationForm::KEY,
            'owner_type' => $this->stage->getMorphClass(),
            'owner_id' => $this->stage->id,
            'field_modes' => ['previous_school' => 'hidden', 'phone' => 'optional'],
            'fields' => [['key' => 'kanoon', 'label' => 'شمارنده کانون', 'type' => 'text', 'required' => true, 'section' => 'education']],
        ]);

        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['previous_school' => 'نباید ذخیره شود', 'phone' => '']))
            ->assertSessionHasErrors('custom.kanoon');
        $this->post('/preregister', $this->validData(['previous_school' => 'نباید ذخیره شود', 'phone' => '', 'custom' => ['kanoon' => 'K-123']]))
            ->assertSessionHasNoErrors();

        $preregistration = Preregistration::withoutTenantScope()->sole();
        $this->assertNull($preregistration->previous_school);
        $this->assertNull($preregistration->phone);
        $this->assertSame('K-123', $preregistration->custom_fields[0]['value']);
    }

    public function test_a_filled_honeypot_is_answered_like_a_success_but_not_saved(): void
    {
        $this->post('/preregister', $this->validData([PreregistrationController::HONEYPOT => 'https://spam.example']))
            ->assertRedirect(route('preregister.create'))
            ->assertSessionHas('status', PreregistrationController::SUBMITTED_MESSAGE);

        $this->assertSame(0, Preregistration::withoutTenantScope()->count());
    }

    public function test_submissions_and_codes_are_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/preregister', [PreregistrationController::HONEYPOT => 'x'])->assertRedirect();
            $this->post('/preregister/mobile/verify', ['mobile' => '09121234567', 'code' => '000000']);
        }

        $this->post('/preregister', [PreregistrationController::HONEYPOT => 'x'])->assertTooManyRequests();
        $this->post('/preregister/mobile/code', ['mobile' => '09121234567'])->assertTooManyRequests();
    }

    public function test_the_birth_year_range_follows_lms(): void
    {
        $year = Jalali::now()->getYear();

        $this->assertSame([$year - 19, $year - 4], StorePreregistrationRequest::birthYearRange());
    }
}

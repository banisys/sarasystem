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
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Tests\Concerns\SubmitsPreregistrations;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class PreregisterFormTest extends TestCase
{
    use RefreshDatabase, SubmitsPreregistrations;

    private Tenant $primary;

    private Tenant $west;

    private Program $program;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create(['name' => 'ستاد', 'slug' => 'head-office']);
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'غرب', 'slug' => 'west']);
        $this->program = Program::factory()->for($this->primary)->create(['title' => 'زبان انگلیسی - مقدماتی']);
    }

    public function test_the_form_lists_the_primary_tenants_active_programs(): void
    {
        Program::factory()->for($this->primary)->inactive()->create(['title' => 'غیرفعال']);
        Program::factory()->for($this->west)->create(['title' => 'برنامه‌ی شعبه']);

        $this->get('/preregister')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Student::Preregister')
                ->where('site.tenant.name', 'ستاد')
                ->has('programs', 1)
                ->where('programs.0.title', 'زبان انگلیسی - مقدماتی')
                ->where('labels.learner', config('sarasystem.learner_label'))
                // The head office is not a tenant to choose.
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
        $this->assertSame($this->program->id, $preregistration->program_id);
        $this->assertSame('زبان انگلیسی - مقدماتی', $preregistration->program_title);
        $this->assertSame($this->west->id, $preregistration->preferred_tenant_id);
        $this->assertSame(PreregistrationStatus::Submitted, $preregistration->status);
        $this->assertSame('2014-04-30', $preregistration->birth_date->toDateString());
        $this->assertNull($preregistration->email);
        $this->assertSame(0, User::count());
    }

    public function test_only_the_program_and_identity_are_required_by_default(): void
    {
        $this->verifyMobile();
        $this->post('/preregister', $this->validData([
            'father_name' => '', 'phone' => '', 'email' => '', 'postal_code' => '', 'city' => '', 'address' => '', 'preferred_tenant_id' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Preregistration::withoutTenantScope()->sole()->father_name);
    }

    public function test_an_adult_learner_can_preregister(): void
    {
        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['birth_date' => (Jalali::now()->getYear() - 45).'/06/01']))->assertSessionHasNoErrors();

        $this->assertSame(1, Preregistration::withoutTenantScope()->count());
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

    public function test_a_foreign_applicant_gives_a_twelve_digit_code(): void
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
        $inactive = Program::factory()->for($this->primary)->inactive()->create();
        $foreign = Program::factory()->for($this->west)->create();

        $this->post('/preregister', $this->validData(['program_id' => '']))->assertSessionHasErrors('program_id');
        $this->post('/preregister', $this->validData(['program_id' => $inactive->id]))->assertSessionHasErrors('program_id');
        $this->post('/preregister', $this->validData(['program_id' => $foreign->id]))->assertSessionHasErrors('program_id');
        $this->post('/preregister', $this->validData(['preferred_tenant_id' => $this->primary->id]))->assertSessionHasErrors('preferred_tenant_id');
        $this->post('/preregister', $this->validData(['national_code' => '1234567890']))->assertSessionHasErrors('national_code');
        $this->post('/preregister', $this->validData(['birth_date' => ($currentYear - 1).'/01/01']))->assertSessionHasErrors('birth_date');
        $this->post('/preregister', $this->validData(['phone' => '12345']))->assertSessionHasErrors('phone');
        $this->post('/preregister', $this->validData(['address' => 'کوتاه']))->assertSessionHasErrors('address');

        $this->assertSame(0, Preregistration::withoutTenantScope()->count());
    }

    public function test_an_organization_asks_its_own_questions_per_program(): void
    {
        // A school asks what only schools need, for one program.
        FormCustomization::withoutTenantScope()->create([
            'tenant_id' => $this->primary->id,
            'form' => PreregistrationForm::KEY,
            'owner_type' => $this->program->getMorphClass(),
            'owner_id' => $this->program->id,
            'field_modes' => ['phone' => 'required', 'birth_date' => 'hidden'],
            'fields' => [
                ['key' => 'previous_school', 'label' => 'نام مدرسه‌ی پیشین', 'type' => 'text', 'required' => true, 'section' => 'other'],
                ['key' => 'last_average', 'label' => 'معدل ترم آخر', 'type' => 'number', 'section' => 'other'],
            ],
        ]);

        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['phone' => '']))->assertSessionHasErrors(['phone', 'custom.previous_school']);
        $this->post('/preregister', $this->validData(['birth_date' => 'نباید ذخیره شود', 'custom' => ['previous_school' => 'دبستان فردوسی', 'last_average' => '۱۹.۵']]))
            ->assertSessionHasNoErrors();

        $preregistration = Preregistration::withoutTenantScope()->sole();
        $this->assertNull($preregistration->birth_date);
        $answers = collect($preregistration->custom_fields)->pluck('value', 'key');
        $this->assertSame('دبستان فردوسی', $answers['previous_school']);
        $this->assertEquals(19.5, $answers['last_average']);
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
}

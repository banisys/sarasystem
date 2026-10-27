<?php

namespace Modules\Employee\Tests\Feature;

use App\Models\FormCustomization;
use App\Models\Tenant;
use App\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\Employee\Enums\Gender;
use Modules\Employee\Forms\JobApplicationForm;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;
use Modules\Employee\Tests\Concerns\SubmitsJobApplications;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * The «همکاری با ما» form as each organization sets it up, for all
 * positions and per position (ADR 0017).
 */
class CustomizableCareersFormTest extends TestCase
{
    use RefreshDatabase, SubmitsJobApplications;

    private Tenant $primary;

    private JobPosition $position;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->position = JobPosition::factory()->for($this->primary)->create(['title' => 'معلم ریاضی']);
    }

    public function test_fields_left_out_are_not_asked_or_stored(): void
    {
        $this->customize(modes: [
            'father_name' => 'hidden', 'email' => 'hidden', 'education' => 'hidden', 'resume' => 'hidden', 'military_status' => 'hidden',
        ]);

        $this->verifyMobile();
        $this->post('/careers', $this->validData([
            'father_name' => 'نباید ذخیره شود',
            'email' => 'not-an-email',
            'education' => [],
            'resume' => null,
            'gender' => Gender::Male->value,
            'military_status' => '',
        ]))->assertSessionHasNoErrors();

        $application = JobApplication::withoutTenantScope()->sole();
        $this->assertNull($application->father_name);
        $this->assertNull($application->email);
        $this->assertNull($application->military_status);
        $this->assertSame([], $application->education);
        $this->assertFalse($application->hasMedia(JobApplication::RESUME_COLLECTION));
    }

    public function test_optional_fields_may_be_left_empty_and_required_ones_may_not(): void
    {
        $this->customize(modes: ['postal_code' => 'optional', 'address' => 'optional', 'work_history' => 'required', 'phone' => 'required']);

        $this->verifyMobile();
        $this->post('/careers', $this->validData(['postal_code' => '', 'address' => '', 'work_history' => [], 'phone' => '']))
            ->assertSessionHasErrors(['work_history', 'phone'])
            ->assertSessionDoesntHaveErrors(['postal_code', 'address']);

        $this->post('/careers', $this->validData(['postal_code' => '', 'address' => '']))->assertSessionHasNoErrors();

        $this->assertNull(JobApplication::withoutTenantScope()->sole()->postal_code);
    }

    public function test_added_fields_are_validated_and_stored_with_a_copy_of_the_field(): void
    {
        $this->customize(fields: [
            ['key' => 'kanoon', 'label' => 'عضو کانون هستید؟', 'type' => 'select', 'required' => true, 'options' => ['بله', 'خیر'], 'section' => 'personal'],
            ['key' => 'children', 'label' => 'تعداد فرزندان', 'type' => 'number', 'required' => false, 'section' => 'personal'],
            ['key' => 'start', 'label' => 'زمان آمادگی برای شروع', 'type' => 'date', 'required' => true, 'section' => 'resume'],
            ['key' => 'rules', 'label' => 'قوانین را می‌پذیرم', 'type' => 'checkbox', 'required' => true, 'section' => 'resume'],
            ['key' => 'skills', 'label' => 'مهارت‌ها', 'type' => 'multi_select', 'options' => ['Word', 'Excel'], 'section' => 'work'],
        ]);

        $this->verifyMobile();
        $this->post('/careers', $this->validData(['custom' => ['kanoon' => 'شاید', 'children' => 'سه', 'start' => '', 'rules' => '0', 'skills' => ['PowerPoint']]]))
            ->assertSessionHasErrors(['custom.kanoon', 'custom.children', 'custom.start', 'custom.rules', 'custom.skills.0']);

        $this->post('/careers', $this->validData(['custom' => [
            'kanoon' => 'بله', 'children' => '۲', 'start' => '۱۴۰۵/۰۸/۰۱', 'rules' => '1', 'skills' => ['Excel'], 'unknown' => 'ignored',
        ]]))->assertSessionHasNoErrors();

        $answers = collect(JobApplication::withoutTenantScope()->sole()->custom_fields)->keyBy('key');
        $this->assertSame(['kanoon', 'children', 'start', 'rules', 'skills'], $answers->keys()->all());
        $this->assertSame('عضو کانون هستید؟', $answers['kanoon']['label']);
        $this->assertSame('بله', $answers['kanoon']['value']);
        $this->assertSame(2, $answers['children']['value']);
        $this->assertSame('2026-10-23', $answers['start']['value']);
        $this->assertTrue($answers['rules']['value']);
        $this->assertSame(['Excel'], $answers['skills']['value']);
    }

    public function test_a_position_can_change_the_form_for_itself_only(): void
    {
        $other = JobPosition::factory()->for($this->primary)->create(['title' => 'سرایدار', 'sort' => 1]);
        $this->customize(modes: ['education' => 'required', 'email' => 'required'], fields: [
            ['key' => 'general', 'label' => 'نحوه‌ی آشنایی با ما', 'type' => 'text', 'required' => true],
        ]);
        $this->customize(owner: $other, modes: ['education' => 'hidden', 'email' => 'optional'], fields: [
            ['key' => 'shift', 'label' => 'شیفت شب', 'type' => 'checkbox'],
        ]);

        $this->get('/careers')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('form.modes.education', 'required')
            ->where('form.fields.0.key', 'general')
            ->where('positions.0.form.modes.education', 'required')
            ->has('positions.0.form.fields', 1)
            ->where('positions.1.form.modes.education', 'hidden')
            ->where('positions.1.form.modes.email', 'optional')
            ->where('positions.1.form.fields.1.key', 'shift'));

        $this->verifyMobile();
        $this->post('/careers', $this->validData(['education' => [], 'email' => '', 'custom' => ['general' => 'اینستاگرام']]))
            ->assertSessionHasErrors(['education', 'email']);

        $this->post('/careers', $this->validData(['job_position_id' => $other->id, 'education' => [], 'email' => '', 'custom' => ['general' => 'اینستاگرام', 'shift' => '1']]))
            ->assertSessionHasNoErrors();

        $application = JobApplication::withoutTenantScope()->sole();
        $this->assertSame([], $application->education);
        $this->assertSame(['general', 'shift'], array_column($application->custom_fields, 'key'));
    }

    public function test_another_tenants_setup_does_not_change_the_form(): void
    {
        $west = Tenant::factory()->create();
        $this->customize(tenant: $west, modes: ['father_name' => 'hidden'], fields: [
            ['key' => 'west_only', 'label' => 'فیلد شعبه', 'type' => 'text', 'required' => true],
        ]);

        $this->get('/careers')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('form.modes.father_name', 'required')
            ->has('form.fields', 0));

        $this->verifyMobile();
        $this->post('/careers', $this->validData(['father_name' => '']))->assertSessionHasErrors('father_name');
    }

    /**
     * @param  array<string, string>  $modes
     * @param  list<array<string, mixed>>  $fields
     */
    private function customize(array $modes = [], array $fields = [], ?JobPosition $owner = null, ?Tenant $tenant = null): void
    {
        FormCustomization::withoutTenantScope()->create([
            'tenant_id' => ($tenant ?? $this->primary)->id,
            'form' => JobApplicationForm::KEY,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->id,
            'field_modes' => $modes,
            'fields' => $fields,
        ]);
    }
}

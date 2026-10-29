<?php

namespace Modules\Student\Tests\Feature;

use App\Guardians\GuardianRelationship;
use App\Guardians\Guardians;
use App\Models\FormCustomization;
use App\Models\Guardianship;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Sms\SmsSender;
use App\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Student\Enums\LoginMobileSource;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;
use Modules\Student\Tests\Concerns\SubmitsPreregistrations;
use Tests\Concerns\BuildsWorkflows;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * A school's preregistration form with the parents and whose mobile the
 * child signs in with, and admission making the parents guardians
 * (ADR 0028).
 */
class PreregistrationParentsTest extends TestCase
{
    use BuildsWorkflows, InteractsWithTenants, RefreshDatabase, SubmitsPreregistrations;

    private const CHILD = '0013542419';

    private const FATHER = '0012345679';

    private const MOTHER = '0019876546';

    private Tenant $primary;

    private Tenant $west;

    private Program $program;

    private FakeSmsSender $sms;

    private User $director;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create(['name' => 'ستاد']);
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create(['name' => 'غرب']);
        $this->director = User::factory()->create();
        $this->makeStaff($this->director, $this->primary);
        $this->workflow = $this->makeWorkflow($this->primary, (new Preregistration)->getMorphClass(), [
            $this->userStep('تأیید مدیر', $this->primary, $this->director),
        ]);
        $this->program = Program::factory()->for($this->primary)->create(['workflow_id' => $this->workflow->id]);
    }

    public function test_by_default_the_form_asks_no_parents(): void
    {
        $this->verifyMobile();
        $this->post('/preregister', $this->validData(['father' => $this->father(), 'login_mobile_from' => 'father']))->assertSessionHasNoErrors();

        $preregistration = Preregistration::withoutTenantScope()->sole();
        $this->assertNull($preregistration->father);
        $this->assertNull($preregistration->login_mobile_from);
        $this->assertSame('09121234567', $preregistration->loginMobile());
    }

    public function test_a_school_form_asks_the_parents_and_the_login_mobile(): void
    {
        $this->schoolForm();
        $this->verifyMobile();

        $this->post('/preregister', $this->validData())->assertSessionHasErrors(['father.name', 'father.national_code', 'father.mobile', 'login_mobile_from']);

        // The optional mother is all or nothing, and not the child.
        $this->post('/preregister', $this->validData([
            'national_code' => self::CHILD,
            'father' => $this->father(), 'mother' => ['name' => 'مریم احمدی', 'national_code' => self::CHILD, 'mobile' => ''], 'login_mobile_from' => 'father',
        ]))->assertSessionHasErrors(['mother.mobile', 'mother.national_code']);

        // The login mobile is one that was typed.
        $this->post('/preregister', $this->validData(['father' => $this->father(), 'login_mobile_from' => 'learner']))
            ->assertSessionHasErrors('login_mobile_from');

        $this->post('/preregister', $this->validData([
            'father' => ['name' => 'علی محمدی', 'national_code' => '۰۰۱۲۳۴۵۶۷۹', 'mobile' => '۰۹۱۲۵۵۵۶۶۷۷'], 'mother' => ['name' => '', 'national_code' => '', 'mobile' => ''],
            'learner_mobile' => '', 'login_mobile_from' => 'father',
        ]))->assertSessionHasNoErrors();

        $preregistration = Preregistration::withoutTenantScope()->sole();
        // jsonb orders the keys its own way.
        $this->assertEquals(['name' => 'علی محمدی', 'national_code' => self::FATHER, 'mobile' => '09125556677'], $preregistration->father);
        $this->assertNull($preregistration->mother);
        $this->assertSame(LoginMobileSource::Father, $preregistration->login_mobile_from);
        $this->assertSame('09125556677', $preregistration->loginMobile());
    }

    public function test_with_both_parents_optional_one_is_needed(): void
    {
        $this->schoolForm(['father' => 'optional']);
        $this->verifyMobile();

        $this->post('/preregister', $this->validData(['learner_mobile' => '09351112233', 'login_mobile_from' => 'learner']))
            ->assertSessionHasErrors(['father' => 'مشخصات دست‌کم یکی از والدین را وارد کنید.']);
    }

    public function test_admission_makes_the_parents_guardians_and_the_child_signs_in_with_the_chosen_mobile(): void
    {
        $case = app(WorkflowEngine::class)->start($this->workflow, Preregistration::factory()->for($this->primary)->create([
            'national_code' => self::CHILD, 'mobile' => '09121234567', 'program_id' => $this->program->id,
            'father' => $this->father(), 'mother' => ['name' => 'مریم احمدی', 'national_code' => self::MOTHER, 'mobile' => '09127778899'],
            'login_mobile_from' => LoginMobileSource::Mother,
        ]));

        app(WorkflowEngine::class)->approve($case, $this->director, null, [
            'tenant_id' => $this->west->id, 'enrolled_at' => '2026-09-23', 'role_ids' => [Role::findOrCreate('student', 'web')->id],
        ]);

        $child = Student::withoutTenantScope()->sole()->user;
        $this->assertSame('09127778899', $child->mobile);

        $links = Guardianship::query()->where('ward_id', $child->id)->with('guardian')->get()->keyBy(fn (Guardianship $link): string => $link->relationship->value);
        $this->assertSame(['father', 'mother'], $links->keys()->sort()->values()->all());
        $this->assertSame('علی محمدی', $links['father']->guardian->name);
        $this->assertFalse($links['father']->provides_login_mobile);
        $this->assertTrue($links['mother']->provides_login_mobile);
        $this->assertContains(app(Guardians::class)->role()->id, $links['mother']->guardian->roleIdsIn($this->west));

        // The child follows the mother's mobile; not after choosing their own.
        $links['mother']->guardian->update(['mobile' => '09120001111']);
        $this->assertSame('09120001111', $child->fresh()->mobile);

        app(Guardians::class)->useLoginMobileOf($child, null, '09359990000');
        $links['mother']->guardian->update(['mobile' => '09120002222']);
        $this->assertSame('09359990000', $child->fresh()->mobile);
        $this->assertFalse($links['mother']->fresh()->provides_login_mobile);
    }

    public function test_an_existing_parent_keeps_their_account_and_the_child_gets_its_mobile(): void
    {
        $father = User::factory()->create(['national_code' => self::FATHER, 'name' => 'علی محمدی (قبلی)', 'mobile' => '09124443322']);
        $case = app(WorkflowEngine::class)->start($this->workflow, Preregistration::factory()->for($this->primary)->create([
            'national_code' => self::CHILD, 'program_id' => $this->program->id, 'father' => $this->father(), 'login_mobile_from' => LoginMobileSource::Father,
        ]));

        app(WorkflowEngine::class)->approve($case, $this->director, null, [
            'tenant_id' => $this->west->id, 'enrolled_at' => '2026-09-23', 'role_ids' => [Role::findOrCreate('student', 'web')->id],
        ]);

        $this->assertSame('علی محمدی (قبلی)', $father->fresh()->name);
        $this->assertSame('09124443322', Student::withoutTenantScope()->sole()->user->mobile);
        $this->assertTrue(Guardianship::query()->where('guardian_id', $father->id)->sole()->relationship === GuardianRelationship::Father);
    }

    /**
     * @return array{name: string, national_code: string, mobile: string}
     */
    private function father(): array
    {
        return ['name' => 'علی محمدی', 'national_code' => self::FATHER, 'mobile' => '09125556677'];
    }

    /**
     * A school's setup: father required, mother and the child's mobile optional, login mobile asked.
     *
     * @param  array<string, string>  $modes
     */
    private function schoolForm(array $modes = []): void
    {
        FormCustomization::query()->create([
            'tenant_id' => $this->primary->id, 'form' => PreregistrationForm::KEY,
            'field_modes' => ['father' => 'required', 'mother' => 'optional', 'learner_mobile' => 'optional', 'login_mobile' => 'required', ...$modes],
            'fields' => [],
        ]);
    }
}

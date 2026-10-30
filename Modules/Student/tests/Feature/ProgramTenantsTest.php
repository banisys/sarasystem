<?php

namespace Modules\Student\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use App\Workflow\WorkflowActionDenied;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Modules\Student\Filament\Resources\Programs\Pages\ListPrograms;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;
use Modules\Student\Services\AdmitStudent;
use Modules\Student\Tests\Concerns\SubmitsPreregistrations;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class ProgramTenantsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase, SubmitsPreregistrations;

    private Tenant $primary;

    private Tenant $west;

    private Tenant $east;

    private Program $program;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create();
        $this->east = Tenant::factory()->create();
        $this->program = Program::factory()->for($this->primary)->create(['all_tenants' => false]);
        $this->program->availableInTenants()->sync([$this->west->id]);
    }

    #[TestWith([1])]
    #[TestWith([2])]
    public function test_a_program_can_be_created_for_one_or_several_tenants(int $count): void
    {
        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->primary);
        $ids = array_slice([$this->west->id, $this->east->id], 0, $count);

        Livewire::test(ListPrograms::class)
            ->callAction('create', data: ['title' => 'برنامهٔ منتخب', 'all_tenants' => false, 'availableInTenants' => $ids])
            ->assertHasNoActionErrors();

        $program = Program::query()->where('title', 'برنامهٔ منتخب')->sole();
        $this->assertFalse($program->all_tenants);
        $this->assertSame($this->primary->id, $program->tenant_id);
        $this->assertEqualsCanonicalizing($ids, $program->availableTenants()->modelKeys());
    }

    public function test_all_tenants_includes_future_active_tenants_and_can_be_selected_when_editing(): void
    {
        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->primary);

        Livewire::test(ListPrograms::class)
            ->callAction(TestAction::make('edit')->table($this->program), ['all_tenants' => true])
            ->assertHasNoActionErrors();

        $future = Tenant::factory()->create();
        $inactive = Tenant::factory()->create(['is_active' => false]);
        $program = $this->program->fresh();
        $this->assertTrue($program->all_tenants);
        $this->assertContains($future->id, $program->availableTenants()->modelKeys());
        $this->assertContains($this->east->id, $program->availableTenants()->modelKeys());
        $this->assertNotContains($inactive->id, $program->availableTenants()->modelKeys());

        Livewire::test(ListPrograms::class)
            ->callAction(TestAction::make('edit')->table($program), ['all_tenants' => false, 'availableInTenants' => [$this->east->id]])
            ->assertHasNoActionErrors();

        $this->assertSame([$this->east->id], $program->fresh()->availableTenants()->modelKeys());
    }

    public function test_selected_mode_requires_at_least_one_active_existing_tenant(): void
    {
        $this->actingInPanel(User::factory()->superAdmin()->create(), $this->primary);
        $inactive = Tenant::factory()->create(['is_active' => false]);

        Livewire::test(ListPrograms::class)
            ->callAction('create', data: ['title' => 'بدون شعبه', 'all_tenants' => false, 'availableInTenants' => []])
            ->assertHasActionErrors(['availableInTenants' => 'required']);

        Livewire::test(ListPrograms::class)
            ->callAction('create', data: ['title' => 'شعبهٔ نامعتبر', 'all_tenants' => false, 'availableInTenants' => [$inactive->id]])
            ->assertHasActionErrors(['availableInTenants' => 'exists']);

        $this->assertSame(1, Program::query()->count());
    }

    public function test_public_program_tenant_choices_are_restricted_and_foreign_programs_are_not_listed(): void
    {
        Program::factory()->for($this->east)->create();
        $inactive = Tenant::factory()->create(['is_active' => false]);
        $this->program->availableInTenants()->attach($inactive);

        $this->get('/preregister')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('programs', 1)
            ->has('programs.0.tenants', 1)
            ->where('programs.0.tenants.0.id', $this->west->id));
    }

    public function test_preregistration_rejects_an_unavailable_tenant_and_accepts_an_assigned_one(): void
    {
        $this->verifyMobile();

        $this->post('/preregister', $this->validData(['preferred_tenant_id' => $this->east->id]))
            ->assertSessionHasErrors('preferred_tenant_id');
        $this->assertSame(0, Preregistration::withoutTenantScope()->count());

        $this->post('/preregister', $this->validData(['preferred_tenant_id' => $this->west->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->west->id, Preregistration::withoutTenantScope()->sole()->preferred_tenant_id);
    }

    public function test_admission_refuses_an_unavailable_tenant_before_creating_a_user(): void
    {
        $applicant = Preregistration::factory()->for($this->primary)->for($this->program)->create();
        $actor = User::factory()->create();

        try {
            app(AdmitStudent::class)->admit($applicant, $actor, ['tenant_id' => $this->east->id, 'enrolled_at' => '2026-10-30']);
            $this->fail('Admission to an unavailable tenant must be refused.');
        } catch (WorkflowActionDenied $exception) {
            $this->assertSame('این برنامه در '.config('sarasystem.tenant_label').' انتخاب‌شده ارائه نمی‌شود.', $exception->getMessage());
        }

        $this->assertSame(0, Student::withoutTenantScope()->count());
        $this->assertSame(1, User::query()->count());
    }
}

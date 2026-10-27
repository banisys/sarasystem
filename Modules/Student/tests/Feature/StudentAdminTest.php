<?php

namespace Modules\Student\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use Database\Seeders\DemoSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Student\Database\Seeders\AdmissionDemoSeeder;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Filament\Resources\Preregistrations\Pages\ListPreregistrations;
use Modules\Student\Filament\Resources\Preregistrations\PreregistrationResource;
use Modules\Student\Filament\Resources\Programs\Pages\ListPrograms;
use Modules\Student\Filament\Resources\Programs\ProgramResource;
use Modules\Student\Filament\Resources\Students\Pages\ListStudents;
use Modules\Student\Filament\Resources\Students\StudentResource;
use Modules\Student\Forms\PreregistrationForm;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class StudentAdminTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $primary;

    private Tenant $west;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->west = Tenant::factory()->create();

        $permissions = collect(['Preregistration', 'Program', 'Student'])
            ->flatMap(fn (string $subject): array => ["ViewAny:{$subject}", "View:{$subject}", "Create:{$subject}", "Update:{$subject}", "Delete:{$subject}"])
            ->all();
        $this->roleWithPermissions('admissions', $permissions);

        $this->manager = User::factory()->create();
        foreach ([$this->primary, $this->west] as $tenant) {
            $this->makeStaff($this->manager, $tenant);
            $this->grantRole($this->manager, $tenant, 'admissions');
        }
    }

    public function test_lists_show_only_the_current_tenants_records(): void
    {
        $ownPreregistration = Preregistration::factory()->for($this->primary)->create();
        $foreignPreregistration = Preregistration::factory()->for($this->west)->create();
        $ownProgram = Program::factory()->for($this->primary)->create();
        $foreignProgram = Program::factory()->for($this->west)->create();

        $this->actingInPanel($this->manager, $this->primary);

        Livewire::test(ListPreregistrations::class)->assertCanSeeTableRecords([$ownPreregistration])->assertCanNotSeeTableRecords([$foreignPreregistration]);
        Livewire::test(ListPrograms::class)->assertCanSeeTableRecords([$ownProgram])->assertCanNotSeeTableRecords([$foreignProgram]);

        $this->get(PreregistrationResource::getUrl('view', ['record' => $foreignPreregistration], tenant: $this->primary))->assertNotFound();
    }

    public function test_the_preregistration_resources_are_only_in_the_primary_tenants_panel(): void
    {
        $this->actingAs($this->manager);

        $this->get(PreregistrationResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(ProgramResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(PreregistrationResource::getUrl('index', tenant: $this->west))->assertForbidden();
        $this->get(ProgramResource::getUrl('index', tenant: $this->west))->assertForbidden();
    }

    public function test_the_learners_list_shows_only_the_current_tenants_learners(): void
    {
        $own = Student::factory()->for($this->west)->create();
        $foreign = Student::factory()->for($this->primary)->create();

        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListStudents::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_a_learners_status_is_edited_in_a_modal(): void
    {
        $student = Student::factory()->for($this->west)->create();

        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListStudents::class)
            ->mountAction(TestAction::make('edit')->table($student))
            ->setActionData(['student_code' => 'S-1', 'status' => StudentStatus::Left->value, 'left_at' => null])
            ->callMountedAction()
            ->assertHasActionErrors(['left_at' => 'required'])
            ->setActionData(['left_at' => now()->toDateString()])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $student->refresh();
        $this->assertSame('S-1', $student->student_code);
        $this->assertSame(StudentStatus::Left, $student->status);
    }

    public function test_the_learner_label_follows_the_installation(): void
    {
        config(['sarasystem.learner_label' => 'هنرآموز', 'sarasystem.learner_plural_label' => 'هنرآموزان']);

        $this->assertSame('هنرآموزان', StudentResource::getPluralModelLabel());
        $this->assertSame('هنرآموزان', StudentResource::getNavigationGroup());
    }

    public function test_programs_are_created_with_a_workflow(): void
    {
        $this->actingInPanel($this->manager, $this->primary);

        Livewire::test(ListPrograms::class)
            ->callAction('create', data: ['title' => 'کارشناسی کامپیوتر', 'is_active' => true])
            ->assertHasNoActionErrors();

        $this->assertSame($this->primary->id, Program::query()->where('title', 'کارشناسی کامپیوتر')->sole()->tenant_id);
    }

    public function test_staff_without_permission_cannot_see_preregistrations(): void
    {
        $clerk = User::factory()->create();
        $this->makeStaff($clerk, $this->primary);

        $this->actingAs($clerk)->get(PreregistrationResource::getUrl('index', tenant: $this->primary))->assertForbidden();
    }

    public function test_the_demo_seeder_sets_up_a_schools_preregistration_and_can_run_again(): void
    {
        $this->seed(DemoSeeder::class);
        $headOffice = Tenant::query()->where('slug', 'head-office')->sole();
        $headOffice->makePrimary();
        User::factory()->create(['email' => 'admin@sarasystem.test']);

        $this->seed(AdmissionDemoSeeder::class);
        $this->seed(AdmissionDemoSeeder::class);

        $workflow = Workflow::withoutTenantScope()->sole();
        $this->assertSame(AdmissionDemoSeeder::WORKFLOW_NAME, $workflow->name);
        $this->assertCount(3, $workflow->stepList());

        $programs = Program::withoutTenantScope()->get();
        $this->assertCount(12, $programs);
        $this->assertTrue($programs->every(fn (Program $program): bool => $program->workflow_id === $workflow->id));

        // The school-only questions are the organization's added fields.
        $form = PreregistrationForm::for($headOffice->refresh());
        $this->assertSame(['birth_certificate', 'birth_place', 'previous_school', 'last_average'], array_map(fn ($field) => $field->key, $form->fields));
        $this->assertTrue($form->requires('father_name'));
    }
}

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
use Modules\Student\Filament\Resources\Grades\GradeResource;
use Modules\Student\Filament\Resources\Grades\Pages\ListGrades;
use Modules\Student\Filament\Resources\Preregistrations\Pages\ListPreregistrations;
use Modules\Student\Filament\Resources\Preregistrations\PreregistrationResource;
use Modules\Student\Filament\Resources\Students\Pages\ListStudents;
use Modules\Student\Models\Grade;
use Modules\Student\Models\Preregistration;
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

        $permissions = collect(['Preregistration', 'Grade', 'Student'])
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
        $ownGrade = Grade::factory()->for($this->primary)->create();
        $foreignGrade = Grade::factory()->for($this->west)->create();

        $this->actingInPanel($this->manager, $this->primary);

        Livewire::test(ListPreregistrations::class)->assertCanSeeTableRecords([$ownPreregistration])->assertCanNotSeeTableRecords([$foreignPreregistration]);
        Livewire::test(ListGrades::class)->assertCanSeeTableRecords([$ownGrade])->assertCanNotSeeTableRecords([$foreignGrade]);

        $this->get(PreregistrationResource::getUrl('view', ['record' => $foreignPreregistration], tenant: $this->primary))->assertNotFound();
    }

    public function test_the_preregistration_resources_are_only_in_the_primary_tenants_panel(): void
    {
        $this->actingAs($this->manager);

        $this->get(PreregistrationResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(GradeResource::getUrl('index', tenant: $this->primary))->assertOk();
        $this->get(PreregistrationResource::getUrl('index', tenant: $this->west))->assertForbidden();
        $this->get(GradeResource::getUrl('index', tenant: $this->west))->assertForbidden();
    }

    public function test_the_students_list_shows_only_the_current_tenants_students(): void
    {
        $own = Student::factory()->for($this->west)->create();
        $foreign = Student::factory()->for($this->primary)->create();

        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListStudents::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_a_students_grade_and_status_are_edited_in_a_modal(): void
    {
        $stage = Grade::factory()->for($this->primary)->create(['title' => 'متوسطه دوم']);
        $tenth = Grade::factory()->childOf($stage)->create(['title' => 'دهم']);
        $student = Student::factory()->for($this->west)->create();

        $this->actingInPanel($this->manager, $this->west);

        Livewire::test(ListStudents::class)
            ->mountAction(TestAction::make('edit')->table($student))
            ->setActionData(['grade_id' => $tenth->id, 'status' => StudentStatus::Left->value, 'left_at' => null])
            ->callMountedAction()
            ->assertHasActionErrors(['left_at' => 'required'])
            ->setActionData(['left_at' => now()->toDateString()])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $student->refresh();
        $this->assertSame('متوسطه دوم - دهم', $student->grade_title);
        $this->assertSame(StudentStatus::Left, $student->status);
    }

    public function test_grades_are_created_under_a_stage(): void
    {
        $stage = Grade::factory()->for($this->primary)->create(['title' => 'دبستان']);

        $this->actingInPanel($this->manager, $this->primary);

        Livewire::test(ListGrades::class)
            ->callAction('create', data: ['title' => 'اول', 'parent_id' => $stage->id, 'is_active' => true, 'sort' => 1])
            ->assertHasNoActionErrors();

        $grade = Grade::query()->where('title', 'اول')->sole();
        $this->assertSame($stage->id, $grade->parent_id);
        $this->assertSame($this->primary->id, $grade->tenant_id);
        $this->assertSame('دبستان - اول', $grade->fullTitle());
    }

    public function test_staff_without_permission_cannot_see_preregistrations(): void
    {
        $clerk = User::factory()->create();
        $this->makeStaff($clerk, $this->primary);

        $this->actingAs($clerk)->get(PreregistrationResource::getUrl('index', tenant: $this->primary))->assertForbidden();
    }

    public function test_the_demo_seeder_adds_the_grades_and_an_admission_workflow_and_can_run_again(): void
    {
        $this->seed(DemoSeeder::class);
        Tenant::query()->where('slug', 'head-office')->sole()->makePrimary();
        User::factory()->create(['email' => 'admin@sarasystem.test']);

        $this->seed(AdmissionDemoSeeder::class);
        $this->seed(AdmissionDemoSeeder::class);

        $stages = Grade::withoutTenantScope()->stages()->with('children')->get();
        $this->assertSame(['دبستان', 'متوسطه اول', 'متوسطه دوم'], $stages->sortBy('sort')->pluck('title')->values()->all());
        $this->assertSame(12, $stages->sum(fn (Grade $stage): int => $stage->children->count()));

        $workflow = Workflow::withoutTenantScope()->sole();
        $this->assertSame(AdmissionDemoSeeder::WORKFLOW_NAME, $workflow->name);
        $this->assertCount(3, $workflow->stepList());
        $this->assertTrue($stages->every(fn (Grade $stage): bool => $stage->workflow_id === $workflow->id));
    }
}

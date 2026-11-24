<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Course\Enums\TeacherEducation;
use Modules\Course\Filament\Resources\Courses\Pages\EditCourse;
use Modules\Course\Filament\Resources\Courses\RelationManagers\InstructorsRelationManager;
use Modules\Course\Filament\Resources\CourseTeachers\CourseTeacherResource;
use Modules\Course\Filament\Resources\CourseTeachers\Pages\ManageCourseTeachers;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseInstructor;
use Modules\Course\Models\CourseTeacher;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CourseTeacherTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $officer = User::factory()->create();
        app(DefaultRoles::class)->install();
        $this->makeStaff($officer, $this->tenant);
        $this->grantRole($officer, $this->tenant, DefaultRoles::EDUCATION_OFFICER);
        $this->actingInPanel($officer, $this->tenant);
    }

    public function test_education_officers_manage_teacher_profiles_without_changing_accounts_or_assignments(): void
    {
        $user = User::factory()->create(['name' => 'مدرس نمونه']);
        $this->grantRole($user, $this->tenant, DefaultRoles::STAFF);
        $course = Course::factory()->create();
        $assignment = CourseInstructor::factory()->for($course)->for($user)->create();

        $this->get(CourseTeacherResource::getUrl(tenant: $this->tenant))->assertOk()->assertSee('افزودن مدرس');

        $data = [
            'user_id' => $user->id,
            'education' => TeacherEducation::Master->value,
            'field_of_study' => 'مدیریت',
            'institution' => 'دانشگاه نمونه',
            'teaching_experience' => 8,
            'description' => '<p>سابقهٔ حرفه‌ای و زمینه‌های تدریس</p>',
        ];
        Livewire::test(ManageCourseTeachers::class)
            ->callAction('create', data: $data)->assertHasNoActionErrors();

        $teacher = CourseTeacher::query()->sole();
        $this->assertSame($this->tenant->id, $teacher->tenant_id);
        $this->assertTrue($teacher->is_active);
        $this->assertSame(TeacherEducation::Master, $teacher->education);
        foreach (['user_id', 'field_of_study', 'institution', 'teaching_experience', 'description'] as $field) {
            $this->assertSame($data[$field], $teacher->{$field});
        }
        $this->assertArrayNotHasKey('short_description', $teacher->getAttributes());

        Livewire::test(ManageCourseTeachers::class)
            ->searchTable($user->name)
            ->assertCanSeeTableRecords([$teacher])
            ->assertTableColumnStateSet('assignments_count', 1, record: $teacher)
            ->callAction(TestAction::make('edit')->table($teacher), data: ['is_active' => false])
            ->assertHasNoActionErrors();
        $this->assertFalse($teacher->refresh()->is_active);

        Livewire::test(ManageCourseTeachers::class)
            ->callAction(TestAction::make('delete')->table($teacher))->assertHasNoActionErrors();

        $this->assertModelMissing($teacher);
        $this->assertModelExists($user);
        $this->assertModelExists($assignment);
        $this->assertSame($user->id, $assignment->refresh()->user_id);
    }

    public function test_teacher_profiles_and_user_selection_are_limited_to_the_current_branch(): void
    {
        $branch = Tenant::factory()->create();
        $foreignUser = User::factory()->create();
        $sharedUser = User::factory()->create();
        $this->grantRole($foreignUser, $branch, DefaultRoles::STAFF);
        $this->grantRole($sharedUser, $branch, DefaultRoles::STAFF);
        $this->grantRole($sharedUser, $this->tenant, DefaultRoles::STAFF);
        $foreign = $branch->execute(fn () => CourseTeacher::factory()->for($sharedUser)->create());
        $data = ['education' => TeacherEducation::Bachelor->value, 'field_of_study' => 'زبان', 'institution' => 'دانشگاه نمونه'];

        Livewire::test(ManageCourseTeachers::class)
            ->assertCanNotSeeTableRecords([$foreign])
            ->callAction('create', data: $data + ['user_id' => $foreignUser->id])
            ->assertHasActionErrors(['user_id']);

        Livewire::test(ManageCourseTeachers::class)
            ->callAction('create', data: $data + ['user_id' => $sharedUser->id])
            ->assertHasNoActionErrors();

        Livewire::test(ManageCourseTeachers::class)
            ->callAction('create', data: $data + ['user_id' => $sharedUser->id])
            ->assertHasActionErrors(['user_id']);

        $this->assertSame($sharedUser->id, CourseTeacher::query()->sole()->user_id);
    }

    public function test_inactive_teachers_cannot_be_newly_assigned_to_a_course(): void
    {
        $teacher = User::factory()->create();
        $this->grantRole($teacher, $this->tenant, DefaultRoles::STAFF);
        $profile = CourseTeacher::factory()->for($teacher)->create(['is_active' => false]);
        $course = Course::factory()->create();
        $owner = ['ownerRecord' => $course, 'pageClass' => EditCourse::class];

        Livewire::test(InstructorsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $teacher->id])
            ->assertHasActionErrors(['user_id']);
        $this->assertFalse($course->instructors()->exists());

        $profile->update(['is_active' => true]);
        Livewire::test(InstructorsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $teacher->id])
            ->assertHasNoActionErrors();

        $this->assertSame($teacher->id, $course->instructors()->sole()->user_id);
    }

    public function test_panel_staff_without_teacher_permissions_cannot_open_the_directory(): void
    {
        $staff = User::factory()->create();
        $this->makeStaff($staff, $this->tenant);

        $this->actingAs($staff)->get(CourseTeacherResource::getUrl(tenant: $this->tenant))->assertForbidden();
    }
}

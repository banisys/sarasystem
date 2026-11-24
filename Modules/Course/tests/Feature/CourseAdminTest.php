<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Pages\CreateCourse;
use Modules\Course\Filament\Resources\Courses\Pages\EditCourse;
use Modules\Course\Filament\Resources\Courses\Pages\ListCourses;
use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\InstructorsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\LessonsRelationManager;
use Modules\Course\Filament\Resources\Courses\RelationManagers\SectionsRelationManager;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseCategory;
use Modules\Course\Models\CourseSection;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CourseAdminTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_officers_create_courses_and_manage_their_content_inside_their_branch(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Tenant::factory()->create();
        [$foreignCategory, $foreignCourse] = $branch->execute(fn (): array => [
            CourseCategory::factory()->create(), Course::factory()->create(),
        ]);
        $this->actAsEducationOfficer($tenant);
        $category = CourseCategory::factory()->create();

        $component = Livewire::test(CreateCourse::class)->fillForm([
            'title' => 'آشنایی با آموزش سازمانی',
            'course_category_id' => $foreignCategory->id,
        ]);
        $component->call('create')->assertHasFormErrors(['course_category_id']);
        $component->fillForm(['course_category_id' => $category->id])->call('create')->assertHasNoFormErrors();

        $course = Course::query()->sole();
        $this->assertSame($tenant->id, $course->tenant_id);
        Livewire::test(ListCourses::class)->assertCanSeeTableRecords([$course])->assertCanNotSeeTableRecords([$foreignCourse]);
        $this->get(CourseResource::getUrl('edit', ['record' => $foreignCourse], tenant: $tenant))->assertNotFound();
        $this->get(CourseResource::getUrl('edit', ['record' => $course], tenant: $tenant))->assertOk()->assertSee(['بخش‌ها', 'درس‌ها', 'ثبت‌نام‌ها']);

        $owner = ['ownerRecord' => $course, 'pageClass' => EditCourse::class];
        Livewire::test(SectionsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['title' => 'شروع', 'position' => 1])
            ->assertHasNoActionErrors();

        $section = $course->sections()->sole();
        $unrelated = CourseSection::factory()->create();
        Livewire::test(LessonsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['title' => 'درس اول', 'course_section_id' => $unrelated->id])
            ->assertHasActionErrors(['course_section_id']);

        Livewire::test(LessonsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: [
                'title' => 'درس اول',
                'course_section_id' => $section->id,
                'body' => 'متن درس',
                'position' => 1,
            ])->assertHasNoActionErrors();

        $this->assertSame('متن درس', $section->lessons()->sole()->body);
    }

    public function test_officers_may_assign_branch_members_but_cannot_assign_users_from_another_branch(): void
    {
        $tenant = Tenant::factory()->create();
        $branch = Tenant::factory()->create();
        $instructor = User::factory()->create();
        $learner = User::factory()->create();
        $outsider = User::factory()->create();
        $this->grantRole($instructor, $tenant, DefaultRoles::INSTRUCTOR);
        $this->grantRole($learner, $tenant, DefaultRoles::LEARNER);
        $this->grantRole($outsider, $branch, DefaultRoles::LEARNER);
        $this->actAsEducationOfficer($tenant);

        $course = Course::factory()->create();
        $owner = ['ownerRecord' => $course, 'pageClass' => EditCourse::class];
        Livewire::test(InstructorsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $instructor->id])
            ->assertHasNoActionErrors();
        $this->assertSame($instructor->id, $course->instructors()->sole()->user_id);

        Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $outsider->id])
            ->assertHasActionErrors(['user_id']);

        Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $learner->id, 'expires_at' => now()->addDay()->toDateTimeString()])
            ->assertHasNoActionErrors();

        Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $learner->id])
            ->assertHasActionErrors(['user_id']);

        $enrollment = $course->enrollments()->sole();
        $this->assertSame($learner->id, $enrollment->user_id);
        $this->assertNotNull($enrollment->expires_at);

        Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('edit')->table($enrollment), data: ['is_revoked' => true])
            ->assertHasNoActionErrors();

        $this->assertTrue($enrollment->refresh()->is_revoked);
    }

    private function actAsEducationOfficer(Tenant $tenant): void
    {
        $officer = User::factory()->create();
        app(DefaultRoles::class)->install();
        $this->makeStaff($officer, $tenant);
        $this->grantRole($officer, $tenant, DefaultRoles::EDUCATION_OFFICER);
        $this->actingInPanel($officer, $tenant);
    }
}

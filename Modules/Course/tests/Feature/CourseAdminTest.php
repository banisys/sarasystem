<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
use Modules\Course\Models\CourseTeacher;
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
        $additionalCategory = CourseCategory::factory()->create();
        $instructor = User::factory()->create();
        $inactiveInstructor = User::factory()->create();
        $outsider = User::factory()->create();
        $this->grantRole($instructor, $tenant, DefaultRoles::INSTRUCTOR);
        $this->grantRole($inactiveInstructor, $tenant, DefaultRoles::INSTRUCTOR);
        CourseTeacher::factory()->for($inactiveInstructor)->create(['is_active' => false]);
        $this->grantRole($outsider, $branch, DefaultRoles::INSTRUCTOR);

        $component = Livewire::test(CreateCourse::class)->fillForm([
            'title' => 'آشنایی با آموزش سازمانی',
            'category_ids' => [$foreignCategory->id],
            'instructor_ids' => [$outsider->id, $inactiveInstructor->id],
        ]);
        $component->call('create')->assertHasFormErrors(['category_ids.0', 'instructor_ids.0', 'instructor_ids.1']);
        $this->assertFalse(Course::query()->exists());
        $component->fillForm([
            'category_ids' => [$category->id, $additionalCategory->id],
            'instructor_ids' => [$instructor->id],
            'type' => 'package',
            'capacity' => 25,
            'description_html' => '<p>مقدمهٔ <strong>دوره</strong></p>',
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-31',
            'has_prepayment' => true,
            'prepayment_amount' => 50000,
        ])->call('create')->assertHasNoFormErrors();

        $course = Course::query()->sole();
        $this->assertSame($tenant->id, $course->tenant_id);
        $this->assertSame($category->id, $course->course_category_id);
        $this->assertSame($additionalCategory->id, $course->additionalCategories()->sole()->id);
        $this->assertSame($instructor->id, $course->instructors()->sole()->user_id);
        $this->assertStringStartsWith('CRS-', $course->code);
        $this->assertSame('package', $course->type);
        $this->assertSame(25, $course->capacity);
        $this->assertSame('مقدمهٔ دوره', $course->description);
        $this->assertSame('<p>مقدمهٔ <strong>دوره</strong></p>', $course->description_html);
        $this->assertSame('2026-12-01', $course->starts_on);
        $this->assertSame('2026-12-31', $course->ends_on);
        $this->assertSame(50000, $course->prepayment_amount);
        Livewire::test(ListCourses::class)->assertCanSeeTableRecords([$course])->assertCanNotSeeTableRecords([$foreignCourse]);
        $this->get(CourseResource::getUrl('edit', ['record' => $foreignCourse], tenant: $tenant))->assertNotFound();
        $this->get(CourseResource::getUrl('edit', ['record' => $course], tenant: $tenant))->assertOk()->assertSee(['بخش‌ها', 'درس‌ها', 'ثبت‌نام‌ها']);

        Livewire::test(EditCourse::class, ['record' => $course->id])
            ->assertFormSet(['category_ids' => [(string) $category->id, (string) $additionalCategory->id], 'has_prepayment' => true])
            ->fillForm(['category_ids' => [$additionalCategory->id, $category->id], 'has_prepayment' => false])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($additionalCategory->id, $course->refresh()->course_category_id);
        $this->assertSame($category->id, $course->additionalCategories()->sole()->id);
        $this->assertNull($course->prepayment_amount);
        $this->assertSame($instructor->id, $course->instructors()->sole()->user_id);

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

    public function test_course_form_validates_schedule_and_prepayment_and_stores_media_privately(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $this->actAsEducationOfficer($tenant);
        $existingCourse = Course::factory()->create(['code' => 'CRS-EXISTING']);

        $this->get(CourseResource::getUrl('create', tenant: $tenant))
            ->assertOk()->assertSee(['اطلاعات کلی', 'رسانه', 'زمان‌بندی', 'قیمت‌گذاری', 'مدرسان', 'گالری تصاویر'])
            ->assertDontSee('جنسیت مخاطب')->assertDontSee('توضیح کوتاه');

        $form = Livewire::test(CreateCourse::class)->fillForm([
            'title' => 'دوره با رسانه',
            'code' => $existingCourse->code,
            'starts_on' => '2026-12-31',
            'ends_on' => '2026-12-01',
            'has_prepayment' => true,
        ]);

        $form->call('create')->assertHasFormErrors(['code', 'ends_on' => 'after_or_equal', 'prepayment_amount' => 'required']);
        $this->assertSame(1, Course::query()->count());

        $form->fillForm([
            'code' => 'CRS-MEDIA',
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-31',
            'has_prepayment' => false,
            'cover_image' => UploadedFile::fake()->image('cover.png'),
            'gallery_images' => [UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('second.png')],
        ])->call('create')->assertHasNoFormErrors();

        $course = Course::query()->where('code', 'CRS-MEDIA')->sole();
        $this->assertCount(2, $course->gallery_images);
        Storage::disk('local')->assertExists([$course->cover_image, ...$course->gallery_images]);

        Livewire::test(EditCourse::class, ['record' => $course->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($course->gallery_images, $course->refresh()->gallery_images);
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

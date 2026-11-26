<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseParticipants;
use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseEnrollment;
use Modules\Course\Models\CourseEnrollmentSettings;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class EnrollCourseFromClassTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Module::isEnabled('Class')) {
            $this->markTestSkipped('Class is disabled.');
        }
    }

    public function test_the_participants_page_enrolls_current_attendees_and_keeps_existing_enrollments(): void
    {
        $primary = Tenant::factory()->create(['is_primary' => true]);
        $branch = Tenant::factory()->create();
        $this->actAsCourseEditor($primary, $branch);
        $class = Classroom::factory()->for($branch)->create();
        $attendee = ClassRole::factory()->for($primary)->attendee()->create(['title' => 'شرکت‌کنندهٔ کلاس']);
        $teacher = ClassRole::factory()->for($primary)->teacher()->create(['title' => 'مدرس کلاس']);
        $members = ClassMember::factory()->for($class)->for($attendee);
        $learner = $members->create();
        $existingLearner = $members->create();
        $members->create(['joined_on' => today()->addDay()]);
        $members->create(['joined_on' => today()->subWeek(), 'left_on' => today()->subDay()]);
        ClassMember::factory()->for($class)->for($teacher)->create();
        $course = Course::factory()->create();
        $existing = CourseEnrollment::factory()->for($course)->create([
            'user_id' => $existingLearner->user_id,
            'is_revoked' => true,
        ]);

        Livewire::withoutLazyLoading();
        $this->get(CourseResource::getUrl('participants', ['record' => $course], tenant: $branch))
            ->assertOk()->assertSee(['ثبت‌نام در دوره', 'از کلاس']);

        $component = Livewire::test(EnrollmentsRelationManager::class, $this->owner($course));
        $action = TestAction::make('enrollFromClass')->table();
        $component->callAction($action, data: ['class_id' => $class->id])
            ->assertHasNoActionErrors()->assertNotified('ثبت‌نام از کلاس انجام شد.');
        $component->callAction($action, data: ['class_id' => $class->id])->assertHasNoActionErrors();

        $this->assertSame([$learner->user_id], $course->enrollments()->active()->pluck('user_id')->all());
        $this->assertSame(2, $course->enrollments()->count());
        $this->assertTrue($existing->refresh()->is_revoked);
    }

    public function test_a_class_from_another_branch_cannot_be_used_and_manual_enrollment_can_be_disabled(): void
    {
        $primary = Tenant::factory()->create(['is_primary' => true]);
        $branch = Tenant::factory()->create();
        $otherBranch = Tenant::factory()->create();
        $this->actAsCourseEditor($primary, $branch);
        $this->makeStaff(auth()->user(), $otherBranch);
        $this->grantRole(auth()->user(), $otherBranch, DefaultRoles::EDUCATION_OFFICER);
        $foreignClass = Classroom::factory()->for($otherBranch)->create();
        $attendee = ClassRole::factory()->for($primary)->attendee()->create(['title' => 'شرکت‌کنندهٔ کلاس']);
        ClassMember::factory()->for($foreignClass)->for($attendee)->create();
        $course = Course::factory()->create();
        $action = TestAction::make('enrollFromClass')->table();

        Livewire::test(EnrollmentsRelationManager::class, $this->owner($course))
            ->callAction($action, data: ['class_id' => $foreignClass->id])->assertHasActionErrors(['class_id']);
        $this->assertFalse($course->enrollments()->exists());

        CourseEnrollmentSettings::factory()->create(['manual_enabled' => false]);
        Livewire::test(EnrollmentsRelationManager::class, $this->owner($course))->assertActionHidden($action);
    }

    /** @return array{ownerRecord: Course, pageClass: class-string<ManageCourseParticipants>} */
    private function owner(Course $course): array
    {
        return ['ownerRecord' => $course, 'pageClass' => ManageCourseParticipants::class];
    }

    private function actAsCourseEditor(Tenant $primary, Tenant $branch): void
    {
        $officer = User::factory()->create();
        app(DefaultRoles::class)->install();
        $this->grantRole($officer, $primary, DefaultRoles::EDUCATION_OFFICER);
        $this->grantRole($officer, $branch, DefaultRoles::EDUCATION_OFFICER);
        $this->makeStaff($officer, $branch);
        $this->actingInPanel($officer, $branch);
    }
}

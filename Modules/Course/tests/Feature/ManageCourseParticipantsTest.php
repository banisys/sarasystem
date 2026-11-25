<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Pages\ListCourses;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseParticipants;
use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseEnrollment;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ManageCourseParticipantsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_the_course_row_opens_only_its_own_participants_for_course_editors(): void
    {
        $tenant = Tenant::factory()->create();
        $otherBranch = Tenant::factory()->create();
        $foreignCourse = $otherBranch->execute(fn (): Course => Course::factory()->create());
        $this->actAsEducationOfficer($tenant);
        $course = Course::factory()->create();
        $participant = CourseEnrollment::factory()->for($course)->create();
        $otherParticipant = CourseEnrollment::factory()->create();
        $url = CourseResource::getUrl('participants', ['record' => $course], tenant: $tenant);
        $action = TestAction::make('participants')->table($course);

        Livewire::test(ListCourses::class)->assertActionHasUrl($action, $url);
        Livewire::withoutLazyLoading();
        $this->get($url)->assertOk()
            ->assertSee(['شرکت‌کنندگان', $course->title, $participant->user->name])
            ->assertDontSee($otherParticipant->user->name);
        $this->get(CourseResource::getUrl('participants', ['record' => $foreignCourse], tenant: $tenant))->assertNotFound();

        $viewer = User::factory()->create();
        $this->makeStaff($viewer, $tenant);
        $this->grantRole($viewer, $tenant, $this->roleWithPermissions('course-viewer', ['ViewAny:Course', 'View:Course'])->name);
        $this->actingInPanel($viewer, $tenant);

        Livewire::test(ListCourses::class)->assertActionHidden($action);
        $this->get($url)->assertForbidden();
    }

    public function test_course_editors_can_enroll_branch_members_and_revoke_access_from_the_participants_page(): void
    {
        $tenant = Tenant::factory()->create();
        $otherBranch = Tenant::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $this->grantRole($member, $tenant, DefaultRoles::LEARNER);
        $this->grantRole($outsider, $otherBranch, DefaultRoles::LEARNER);
        $this->actAsEducationOfficer($tenant);
        $course = Course::factory()->create();
        $owner = [
            'ownerRecord' => $course,
            'pageClass' => ManageCourseParticipants::class,
        ];

        Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $outsider->id])
            ->assertHasActionErrors(['user_id']);
        $component = Livewire::test(EnrollmentsRelationManager::class, $owner)
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $member->id])
            ->assertHasNoActionErrors();

        $enrollment = $course->enrollments()->active()->sole();
        $this->assertSame($member->id, $enrollment->user_id);
        $this->assertDatabaseCount(CourseEnrollment::class, 1);

        $component->callAction(TestAction::make('edit')->table($enrollment), data: ['is_revoked' => true])
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

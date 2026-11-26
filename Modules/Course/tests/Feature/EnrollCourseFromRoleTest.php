<?php

namespace Modules\Course\Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Pages\ManageCourseParticipants;
use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseEnrollment;
use Modules\Course\Models\CourseEnrollmentSettings;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class EnrollCourseFromRoleTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_enrollment_uses_the_role_assignment_in_the_selected_branch(): void
    {
        $primary = Tenant::factory()->create(['is_primary' => true]);
        $north = Tenant::factory()->create();
        $south = Tenant::factory()->create();
        $this->actAsCourseEditor($primary, $north, ['ViewAny:User']);
        $matched = User::factory()->create();
        $wrongBranch = User::factory()->create();
        $this->grantRole($matched, $south, 'tutor');
        $this->grantRole($wrongBranch, $north, 'tutor');
        $this->grantRole($wrongBranch, $south, 'assistant');
        $role = Role::findByName('tutor', 'web');
        $course = Course::factory()->create();

        Livewire::withoutLazyLoading();
        $this->get(CourseResource::getUrl('participants', ['record' => $course], tenant: $north))
            ->assertOk()->assertSee(['ثبت‌نام در دوره', 'بر اساس نقش']);
        Livewire::test(EnrollmentsRelationManager::class, $this->owner($course))
            ->callAction(TestAction::make('enrollFromRole')->table(), data: ['scope' => $south->id, 'role_id' => $role->id])
            ->assertHasNoActionErrors()->assertNotified('ثبت‌نام بر اساس نقش انجام شد.');

        $this->assertSame([$matched->id], $course->enrollments()->pluck('user_id')->all());
    }

    public function test_a_shared_role_can_enroll_users_from_all_branches_without_duplicates_or_reactivating_access(): void
    {
        $primary = Tenant::factory()->create(['is_primary' => true]);
        $branch = Tenant::factory()->create();
        $this->actAsCourseEditor($primary, $branch, ['ViewAny:User']);
        $shared = User::factory()->create();
        $this->grantRole($shared, $primary, 'tutor');
        $this->grantRole($shared, $branch, 'tutor');
        $other = User::factory()->create();
        $this->grantRole($other, $branch, 'tutor');
        $role = Role::findByName('tutor', 'web');
        $course = Course::factory()->create();
        $revoked = CourseEnrollment::factory()->for($course)->for($other)->create(['is_revoked' => true]);

        $component = Livewire::test(EnrollmentsRelationManager::class, $this->owner($course));
        $data = ['scope' => 'all', 'role_id' => $role->id];
        $component->callAction(TestAction::make('enrollFromRole')->table(), data: $data)->assertHasNoActionErrors();
        $component->callAction(TestAction::make('enrollFromRole')->table(), data: $data)->assertHasNoActionErrors();

        $this->assertSame([$shared->id], $course->enrollments()->active()->pluck('user_id')->all());
        $this->assertSame(2, $course->enrollments()->count());
        $this->assertTrue($revoked->refresh()->is_revoked);
    }

    public function test_branch_role_cannot_be_used_for_the_all_branches_scope(): void
    {
        $primary = Tenant::factory()->create(['is_primary' => true]);
        $branch = Tenant::factory()->create();
        $this->actAsCourseEditor($primary, $branch, ['ViewAny:User']);
        setPermissionsTeamId($branch->id);
        $role = Role::query()->create(['name' => 'local-tutor', 'guard_name' => 'web', 'tenant_id' => $branch->id]);
        $local = User::factory()->create();
        $local->assignRole($role);
        $course = Course::factory()->create();
        $action = TestAction::make('enrollFromRole')->table();

        Livewire::test(EnrollmentsRelationManager::class, $this->owner($course))
            ->callAction($action, data: ['scope' => 'all', 'role_id' => $role->id])->assertHasActionErrors(['role_id']);
        $this->assertFalse($course->enrollments()->exists());

        Livewire::test(EnrollmentsRelationManager::class, $this->owner($course))
            ->callAction($action, data: ['scope' => $branch->id, 'role_id' => $role->id])->assertHasNoActionErrors();
        $this->assertSame([$local->id], $course->enrollments()->pluck('user_id')->all());
    }

    public function test_a_branch_user_directory_permission_cannot_enroll_users_from_other_branches(): void
    {
        $primary = Tenant::factory()->create(['is_primary' => true]);
        $north = Tenant::factory()->create();
        $south = Tenant::factory()->create();
        $actor = $this->actAsCourseEditor($primary, $north);
        $this->grantRole($actor, $north, $this->roleWithPermissions('branch-directory', ['ViewAny:User'])->name);
        $this->grantRole(User::factory()->create(), $south, 'tutor');
        $local = User::factory()->create();
        $this->grantRole($local, $north, 'tutor');
        $role = Role::findByName('tutor', 'web');
        $course = Course::factory()->create();
        $action = TestAction::make('enrollFromRole')->table();

        foreach (['all', $south->id] as $scope) {
            Livewire::test(EnrollmentsRelationManager::class, $this->owner($course))
                ->callAction($action, data: ['scope' => $scope, 'role_id' => $role->id])->assertHasActionErrors(['scope']);
        }
        $this->assertFalse($course->enrollments()->exists());

        Livewire::test(EnrollmentsRelationManager::class, $this->owner($course))
            ->callAction($action, data: ['scope' => $north->id, 'role_id' => $role->id])->assertHasNoActionErrors();
        $this->assertSame([$local->id], $course->enrollments()->pluck('user_id')->all());

        CourseEnrollmentSettings::factory()->create(['manual_enabled' => false]);
        Livewire::test(EnrollmentsRelationManager::class, $this->owner($course))->assertActionHidden($action);
    }

    /** @return array{ownerRecord: Course, pageClass: class-string<ManageCourseParticipants>} */
    private function owner(Course $course): array
    {
        return ['ownerRecord' => $course, 'pageClass' => ManageCourseParticipants::class];
    }

    /** @param list<string> $permissions */
    private function actAsCourseEditor(Tenant $primary, Tenant $branch, array $permissions = []): User
    {
        $actor = User::factory()->create();
        $role = $this->roleWithPermissions('course-editor', ['ViewAny:Course', 'View:Course', 'Update:Course', ...$permissions]);
        $this->grantRole($actor, $primary, $role->name);
        $this->makeStaff($actor, $primary);
        $this->makeStaff($actor, $branch);
        $this->actingInPanel($actor, $branch);

        return $actor;
    }
}

<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Modules\Course\Filament\Pages\CourseEnrollmentMethods;
use Modules\Course\Filament\Resources\Courses\Pages\EditCourse;
use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseEnrollment;
use Modules\Course\Models\CourseEnrollmentSettings;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CourseEnrollmentMethodsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_head_office_officers_share_enrollment_methods_across_branches(): void
    {
        $tenant = Tenant::factory()->create(['is_primary' => true]);
        $otherBranch = Tenant::factory()->create();
        $officer = User::factory()->create();
        app(DefaultRoles::class)->install();
        $this->makeStaff($officer, $tenant);
        $this->makeStaff($officer, $otherBranch);
        $this->grantRole($officer, $tenant, DefaultRoles::EDUCATION_OFFICER);
        $this->actingInPanel($officer, $tenant);

        $this->get(CourseEnrollmentMethods::getUrl(tenant: $tenant))->assertOk()->assertSee('روش‌های ثبت‌نام');
        $this->get(CourseEnrollmentMethods::getUrl(tenant: $otherBranch))->assertOk();
        $this->actingInPanel($officer, $otherBranch);

        $page = Livewire::test(CourseEnrollmentMethods::class)
            ->assertFormSet(['manual_name' => 'ثبت‌نام دستی', 'manual_enabled' => true, 'free_enabled' => true])
            ->fillForm(['manual_name' => ''])->call('save')->assertHasFormErrors(['manual_name' => 'required']);
        $page->fillForm([
            'manual_name' => 'ثبت‌نام توسط کارشناس',
            'manual_enabled' => false,
            'free_name' => 'ثبت‌نام مستقیم',
            'free_enabled' => false,
        ])->call('save')->assertHasNoFormErrors()->assertNotified('تنظیمات ثبت‌نام ذخیره شد.');
        $page->fillForm(['manual_enabled' => true])->call('save')->assertHasNoFormErrors();

        $this->assertDatabaseCount(CourseEnrollmentSettings::class, 1);
        $this->assertDatabaseHas(CourseEnrollmentSettings::class, [
            'manual_name' => 'ثبت‌نام توسط کارشناس',
            'manual_enabled' => true,
            'free_name' => 'ثبت‌نام مستقیم',
            'free_enabled' => false,
        ]);
        $this->actingInPanel($officer, $tenant);
        Livewire::test(CourseEnrollmentMethods::class)->assertFormSet(['free_name' => 'ثبت‌نام مستقیم', 'free_enabled' => false]);
    }

    public function test_disabling_free_enrollment_hides_the_offer_and_blocks_new_enrollment(): void
    {
        $tenant = Tenant::factory()->create(['is_primary' => true]);
        $learner = User::factory()->create();
        $this->grantRole($learner, $tenant, DefaultRoles::LEARNER);
        $course = $tenant->execute(function (): Course {
            CourseEnrollmentSettings::factory()->create(['free_enabled' => false]);

            return Course::factory()->create(['is_published' => true, 'allows_self_enrollment' => true]);
        });

        $this->get(route('courses.catalog'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('courses.data.0.allows_self_enrollment', false));
        $this->get(route('courses.catalog', $course))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('course.allows_self_enrollment', false));
        $this->actingAs($learner)->get(route('courses.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('courses.data.0.allows_self_enrollment', false));
        $this->get(route('courses.show', $course))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canEnroll', false));
        $this->post(route('courses.enroll', $course))->assertForbidden();
        $this->assertDatabaseCount(CourseEnrollment::class, 0);

        $tenant->execute(fn () => CourseEnrollmentSettings::current()->update(['free_enabled' => true]));
        $this->post(route('courses.enroll', $course))->assertRedirect(route('courses.show', $course));
        $tenant->execute(fn () => CourseEnrollmentSettings::current()->update(['free_enabled' => false]));
        $this->get(route('courses.show', $course))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('enrolled', true));
    }

    public function test_disabling_manual_enrollment_blocks_creation_and_keeps_existing_enrollments_editable(): void
    {
        $tenant = Tenant::factory()->create(['is_primary' => true]);
        $officer = User::factory()->create();
        $learner = User::factory()->create();
        app(DefaultRoles::class)->install();
        $this->makeStaff($officer, $tenant);
        $this->grantRole($officer, $tenant, DefaultRoles::EDUCATION_OFFICER);
        $this->grantRole($learner, $tenant, DefaultRoles::LEARNER);
        $this->actingInPanel($officer, $tenant);
        $course = Course::factory()->create();
        $enrollment = CourseEnrollment::factory()->for($course)->for($learner)->create();
        CourseEnrollmentSettings::factory()->create(['manual_enabled' => false]);

        $component = Livewire::test(EnrollmentsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class]);
        $create = TestAction::make('create')->table();
        $component->assertActionHidden($create)->mountAction($create)->assertSet('mountedActions', []);
        $this->assertDatabaseCount(CourseEnrollment::class, 1);

        $component->callAction(TestAction::make('edit')->table($enrollment), data: ['is_revoked' => true])
            ->assertHasNoActionErrors();
        $this->assertTrue($enrollment->refresh()->is_revoked);
    }
}

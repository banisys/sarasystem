<?php

namespace Modules\Course\Tests\Feature;

use App\Authorization\DefaultRoles;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Course\Integrations\CourseExamSource;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseEnrollment;
use Modules\Course\Models\CourseLesson;
use Modules\Course\Models\CourseSection;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CourseExamTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_lesson_exams_use_the_exam_module_and_require_active_course_enrollment(): void
    {
        if (! Module::isEnabled('Exam')) {
            $this->markTestSkipped('Exam is disabled.');
        }

        $tenant = Tenant::factory()->create(['is_primary' => true]);
        $branch = Tenant::factory()->create();
        $officer = User::factory()->create();
        $learner = User::factory()->create();
        $outsider = User::factory()->create();
        app(DefaultRoles::class)->install();
        $this->grantRole($officer, $tenant, DefaultRoles::EDUCATION_OFFICER);
        $this->grantRole($learner, $branch, DefaultRoles::LEARNER);
        $this->grantRole($outsider, $branch, DefaultRoles::LEARNER);

        [$course, $lesson, $bank] = $tenant->execute(function () use ($tenant, $officer): array {
            $course = Course::factory()->create(['is_published' => true, 'allows_self_enrollment' => true]);
            $lesson = CourseLesson::factory()->for(CourseSection::factory()->for($course), 'section')
                ->create(['is_preview' => true]);
            $bank = QuestionBank::factory()->for($tenant)->create(['owner_id' => $officer->id]);
            Question::factory()->for($tenant)->descriptive()
                ->create(['owner_id' => $officer->id])->banks()->attach($bank);

            return [$course, $lesson, $bank];
        });

        $context = [CourseExamSource::TYPE, $lesson->id];
        $contextUrl = route('exams.context', $context);
        $lessonUrl = route('courses.lesson', [$course, $lesson]);

        $this->actingAs($officer)->get($contextUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('context.host_label', 'درس')->where('context.uses_host_window', false)->where('context.can_manage', true));
        $this->post(route('exams.context.store', $context), [
            'title' => 'آزمون درس',
            'duration_minutes' => 10,
            'total_score' => 20,
            'passing_score' => 12,
            'banks' => [['bank_id' => $bank->id, 'question_count' => 1]],
        ])->assertSessionHasNoErrors()->assertRedirect($contextUrl);

        $exam = Exam::withoutTenantScope()->where('context_type', CourseExamSource::TYPE)->sole();
        $this->assertSame($tenant->id, $exam->tenant_id);
        $this->assertNull($exam->starts_at);
        $this->assertNull($exam->ends_at);
        $this->assertFalse($exam->is_published);
        $examUrl = route('exams.show', $exam);

        $this->patch(route('exams.context.publish', [...$context, $exam]), ['is_published' => true])->assertRedirect();
        $this->actingAs($learner)->withSession([ResolveCurrentTenant::SESSION_KEY => $branch->id])->get($lessonUrl)->assertInertia(fn (Assert $page) => $page->where('examUrl', null));
        $this->get($contextUrl)->assertNotFound();
        $this->get($examUrl)->assertNotFound();
        $this->actingAs($outsider)->get($contextUrl)->assertNotFound();
        $this->get($examUrl)->assertNotFound();

        $this->actingAs($learner)->post(route('courses.enroll', $course))->assertRedirect();
        $this->get($lessonUrl)->assertInertia(fn (Assert $page) => $page->where('examUrl', $contextUrl));
        $this->get($contextUrl)->assertSessionHas(ResolveCurrentTenant::SESSION_KEY, $branch->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.current.id', $branch->id)->where('context.can_manage', false)
                ->where('context.back_url', $lessonUrl)->where('exams.0.take_url', $examUrl));
        $this->get($examUrl)->assertOk()->assertSessionHas(ResolveCurrentTenant::SESSION_KEY, $branch->id);
        $this->post(route('exams.attempt.start', $exam))->assertRedirect($examUrl)
            ->assertSessionHas(ResolveCurrentTenant::SESSION_KEY, $branch->id);

        $unaffiliated = User::factory()->create();
        $this->actingAs($unaffiliated)->post(route('courses.enroll', $course))->assertRedirect();
        $this->get($examUrl)->assertOk()->assertSessionMissing(ResolveCurrentTenant::SESSION_KEY);

        $this->actingAs($learner)->withSession([ResolveCurrentTenant::SESSION_KEY => $branch->id]);

        $course->enrollments()->whereBelongsTo($learner, 'user')->sole()->update(['is_revoked' => true]);
        $this->get($examUrl)->assertNotFound();
    }

    public function test_preexisting_lesson_exams_keep_working_after_their_original_branch_closes(): void
    {
        if (! Module::isEnabled('Exam')) {
            $this->markTestSkipped('Exam is disabled.');
        }

        Tenant::factory()->create(['is_primary' => true]);
        $oldBranch = Tenant::factory()->create(['is_active' => false]);
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_published' => true]);
        $lesson = CourseLesson::factory()->for(CourseSection::factory()->for($course), 'section')->create();
        CourseEnrollment::factory()->for($course)->for($user)->create();
        $exam = Exam::factory()->for($oldBranch)->create([
            'context_type' => CourseExamSource::TYPE,
            'context_id' => $lesson->id,
        ]);

        $this->actingAs($user)
            ->get(route('exams.context', [CourseExamSource::TYPE, $lesson->id]))
            ->assertOk()->assertSessionMissing(ResolveCurrentTenant::SESSION_KEY)
            ->assertInertia(fn (Assert $page) => $page->where('exams.0.take_url', route('exams.show', $exam)));
        $this->get(route('exams.show', $exam))->assertOk();
    }
}

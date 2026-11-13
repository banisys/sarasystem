<?php

namespace Modules\Class\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\ClassSession;
use Modules\Exam\Enums\AttemptStatus;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Support\ExamContexts;
use Nwidart\Modules\Facades\Module;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SessionExamsTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private User $teacher;

    private User $learner;

    private ClassSession $session;

    private Exam $template;

    private ClassMember $membership;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Module::isEnabled('Exam')) {
            $this->markTestSkipped('Exam is disabled.');
        }
        $this->freezeTime();
        $this->tenant = Tenant::factory()->create();
        $this->tenant->makeCurrent();
        $this->teacher = User::factory()->create();
        $this->learner = User::factory()->create();
        $this->grantRole($this->teacher, $this->tenant, 'teacher');
        $this->grantRole($this->learner, $this->tenant, 'student');
        $classroom = Classroom::factory()->for($this->tenant)->create();
        $this->session = ClassSession::factory()->for($classroom)->taughtBy($this->teacher)->create([
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
        ]);
        $this->membership = ClassMember::factory()->for($classroom)->for($this->learner, 'user')
            ->for(ClassRole::factory()->for($this->tenant)->attendee())->create(['joined_on' => today()->subDay()]);
        $bank = QuestionBank::factory()->for($this->tenant)->create();
        Question::factory()->for($this->tenant)->descriptive()->create(['score' => 4, 'model_answer' => 'راه‌حل نمونه'])->banks()->attach($bank);
        $this->template = Exam::factory()->for($this->tenant)->create(['max_attempts' => 1, 'total_score' => 20]);
        $this->template->sections()->create(['question_bank_id' => $bank->id, 'question_count' => 1]);
    }

    public function test_assigning_copies_the_exam_and_is_repeat_safe_with_independent_results_per_session(): void
    {
        $this->template->participantUsers()->attach($this->teacher);
        $first = $this->assign();
        $this->assign();
        $this->assertSame(1, Exam::query()->whereNotNull('context_type')->count());
        $this->assertSame(0, $first->participantUsers()->count());
        $this->assertSame(1, $first->sections()->count());
        $this->assertTrue($first->starts_at->equalTo($this->session->starts_at));
        $this->assertTrue($first->ends_at->equalTo($this->session->ends_at));

        $secondSession = ClassSession::factory()->for($this->session->classroom)->taughtBy($this->teacher)->create([
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
        ]);
        $second = $this->assign($secondSession);
        $this->assertNotSame($first->id, $second->id);
        $this->asLearner();
        $this->post(route('exams.attempt.start', $first))->assertRedirect();
        $this->post(route('exams.attempt.submit', $first), ['answers' => []])->assertRedirect();
        $this->post(route('exams.attempt.start', $first))->assertSessionHasErrors('exam');
        $this->post(route('exams.attempt.start', $second))->assertRedirect();
        $this->assertSame(1, $first->attempts()->count());
        $this->assertSame(1, $second->attempts()->count());
        $this->assertSame(0, $this->template->attempts()->count());
    }

    public function test_membership_on_the_session_date_controls_listing_and_direct_access(): void
    {
        $exam = $this->assign();
        $this->asLearner();
        $this->get(route('exams.index'))->assertInertia(fn (AssertableInertia $page) => $page->has('exams', 1)->where('exams.0.id', $exam->id));
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page->where('context.can_manage', false)->where('results', null)->has('templates', 0));
        $this->get(route('my-classes.session', $this->session))->assertInertia(fn (AssertableInertia $page) => $page->where('session.exams_url', $this->url()));
        $exam->participantUsers()->attach($this->learner);
        $this->membership->update(['left_on' => today()->subDay()]);
        $this->get(route('exams.show', $exam))->assertNotFound();
        $this->get(route('exams.index'))->assertInertia(fn (AssertableInertia $page) => $page->has('exams', 0));
        $this->membership->update(['left_on' => null, 'joined_on' => today()->addDay()]);
        $this->post(route('exams.attempt.start', $exam))->assertNotFound();
        $this->membership->update(['joined_on' => today(), 'left_on' => today()]);
        $this->get(route('exams.show', $exam))->assertOk();
    }

    public function test_the_assigner_can_delete_an_unused_copy_and_assign_the_template_again(): void
    {
        $exam = $this->assign();
        $section = $exam->sections()->sole();
        $delete = $this->deleteUrl($exam);
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page->where('exams.0.delete_url', $delete));

        $this->delete($delete)->assertRedirect($this->url())->assertSessionHas('status', 'آزمون از این جلسه حذف شد.');
        $this->assertModelMissing($exam);
        $this->assertModelMissing($section);
        $this->assertModelExists($this->template);
        $this->assertNotSame($exam->id, $this->assign()->id);
    }

    public function test_a_started_attempt_blocks_deletion_even_before_submission(): void
    {
        $exam = $this->assign();
        $this->asLearner();
        $this->post(route('exams.attempt.start', $exam))->assertRedirect();
        $attempt = $exam->attempts()->sole();

        $this->asTeacher();
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page->where('exams.0.delete_url', null));
        $this->from($this->url())->delete($this->deleteUrl($exam))->assertRedirect($this->url())
            ->assertSessionHasErrors(['delete_exam' => 'حذف ممکن نیست؛ دست‌کم یک نفر این آزمون را شروع کرده است.']);
        $this->assertModelExists($exam);
        $this->assertModelExists($attempt);
        $this->assertSame(1, $attempt->answers()->count());
    }

    public function test_a_session_manager_cannot_claim_or_delete_another_users_copy(): void
    {
        $exam = $this->assign();
        $substitute = User::factory()->create();
        $this->grantRole($substitute, $this->tenant, 'teacher');
        $this->session->teachers()->updateExistingPivot($this->teacher->id, ['substitute_id' => $substitute->id]);
        $this->actingAs($substitute)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->tenant->id]);

        $this->post($this->url(), ['template_id' => $this->template->id])->assertRedirect($this->url());
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('context.can_manage', true)->where('exams.0.delete_url', null));
        $this->delete($this->deleteUrl($exam))->assertForbidden();
        $this->assertSame($this->teacher->id, $exam->fresh()->assigned_by_id);
    }

    public function test_copies_without_an_assigner_require_the_existing_exam_delete_permission(): void
    {
        $exam = $this->assign();
        $exam->forceFill(['assigned_by_id' => null])->save();
        $delete = $this->deleteUrl($exam);
        $this->delete($delete)->assertForbidden();

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->tenant->id]);
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page->where('exams.0.delete_url', $delete));
        $this->delete($delete)->assertRedirect($this->url());
        $this->assertModelMissing($exam);
    }

    public function test_deletion_cannot_target_an_exam_outside_its_session_or_tenant(): void
    {
        $otherSession = ClassSession::factory()->for($this->session->classroom)->taughtBy($this->teacher)->create();
        $otherCopy = $this->assign($otherSession);
        $foreign = Exam::factory()->for(Tenant::factory())->create();

        foreach ([$otherCopy, $this->template, $foreign] as $exam) {
            $this->delete($this->deleteUrl($exam))->assertNotFound();
            $this->assertModelExists($exam);
        }
    }

    public function test_a_learner_cannot_assign_publish_review_or_grade(): void
    {
        $exam = $this->assign();
        $this->asLearner();
        $this->post($this->url(), ['template_id' => $this->template->id])->assertForbidden();
        $this->patch(route('exams.context.publish', ['class-session', $this->session->id, $exam]), ['is_published' => false])->assertForbidden();
        $this->delete($this->deleteUrl($exam))->assertForbidden();
        $attempt = ExamAttempt::factory()->for($exam)->for($this->learner, 'user')->create();
        $this->get(route('exams.context.review', ['class-session', $this->session->id, $attempt]))->assertForbidden();
        $this->post(route('exams.context.grade', ['class-session', $this->session->id, $attempt]), ['scores' => []])->assertForbidden();
        $this->assertTrue($exam->fresh()->is_published);
    }

    public function test_foreign_sessions_templates_and_attempts_are_rejected(): void
    {
        $exam = $this->assign();
        $foreignTenant = Tenant::factory()->create();
        $foreignTemplate = Exam::factory()->for($foreignTenant)->create();
        $foreignSession = ClassSession::factory()->for(Classroom::factory()->for($foreignTenant))->taughtBy($this->teacher)->create();
        $this->post($this->url(), ['template_id' => $foreignTemplate->id])->assertSessionHasErrors('template_id');
        $this->get($this->url($foreignSession))->assertNotFound();
        $this->post($this->url($foreignSession), ['template_id' => $this->template->id])->assertNotFound();
        $foreignAttempt = ExamAttempt::factory()->for($foreignTemplate)->create();
        $this->get(route('exams.context.review', ['class-session', $this->session->id, $foreignAttempt]))->assertNotFound();
        $unrelated = ExamAttempt::factory()->for($this->template)->create();
        $this->get(route('exams.context.review', ['class-session', $this->session->id, $unrelated]))->assertNotFound();
        $this->assertSame(0, $exam->attempts()->count());
    }

    public function test_cancelled_deleted_and_unregistered_contexts_deny_direct_exam_access(): void
    {
        $exam = $this->assign();
        $this->asLearner();
        $this->session->update(['status' => 'cancelled']);
        $this->get($this->url())->assertNotFound();
        $this->post(route('exams.attempt.start', $exam))->assertNotFound();
        $this->session->update(['status' => 'scheduled']);
        $this->app->instance(ExamContexts::class, new ExamContexts);
        $this->get(route('exams.show', $exam))->assertNotFound();
        $this->session->delete();
        $this->assertNotNull($exam->fresh());
        $this->get(route('exams.show', $exam))->assertNotFound();
    }

    public function test_teacher_can_review_and_grade_written_answers_without_global_exam_admin_permissions(): void
    {
        $exam = $this->assign();
        $this->asLearner();
        $this->post(route('exams.attempt.start', $exam))->assertRedirect();
        $attempt = $exam->attempts()->sole();
        $answer = $attempt->answers()->sole();
        $this->post(route('exams.attempt.submit', $exam), ['answers' => [$answer->id => 'پاسخ من']])->assertRedirect();
        $this->asTeacher();
        $review = route('exams.context.review', ['class-session', $this->session->id, $attempt]);
        $grade = route('exams.context.grade', ['class-session', $this->session->id, $attempt]);
        $this->get($review)->assertInertia(fn (AssertableInertia $page) => $page->component('Exam::Review')
            ->where('can_grade', true)
            ->where('answers.0.answer', 'پاسخ من')
            ->where('answers.0.correct', 'راه‌حل نمونه'));
        $this->post($grade, ['scores' => [$answer->id => 5]])->assertSessionHasErrors('scores');
        $this->assertNull($answer->fresh()->score);
        $this->post($grade, ['scores' => [$answer->id => 3]])->assertRedirect($review);
        $this->assertSame(15.0, $attempt->fresh()->score);
        $this->assertSame(AttemptStatus::Completed, $attempt->fresh()->status);
        $this->assertSame($this->teacher->id, $answer->fresh()->graded_by);
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page->where('results.data.0.score', 15));
    }

    public function test_running_attempts_and_foreign_answers_cannot_be_graded(): void
    {
        $exam = $this->assign();
        $this->asLearner();
        $this->post(route('exams.attempt.start', $exam));
        $attempt = $exam->attempts()->sole();
        $answer = $attempt->answers()->sole();
        $this->asTeacher();
        $grade = route('exams.context.grade', ['class-session', $this->session->id, $attempt]);
        $this->post($grade, ['scores' => [$answer->id => 2]])->assertSessionHasErrors('scores');
        $this->assertNull($answer->fresh()->score);
        $this->asLearner();
        $this->post(route('exams.attempt.submit', $exam), ['answers' => []]);
        $this->asTeacher();
        $this->post($grade, ['scores' => [$answer->id => 2, 999999 => 1]])->assertSessionHasErrors('scores');
        $this->assertSame(0.0, $answer->fresh()->score);
    }

    public function test_unpublishing_blocks_new_and_existing_attempt_requests_without_deleting_results(): void
    {
        $exam = $this->assign();
        $this->asLearner();
        $this->post(route('exams.attempt.start', $exam))->assertRedirect();
        $this->asTeacher();
        $this->patch(route('exams.context.publish', ['class-session', $this->session->id, $exam]), ['is_published' => false])->assertRedirect();
        $this->asLearner();
        $this->put(route('exams.attempt.save', $exam), ['answers' => []])->assertNotFound();
        $this->assertSame(1, $exam->attempts()->count());
    }

    public function test_guests_and_unrelated_teachers_cannot_open_the_context(): void
    {
        $this->get($this->url())->assertRedirect(route('login'));
        $outsider = User::factory()->create();
        $this->grantRole($outsider, $this->tenant, 'teacher');
        $this->actingAs($outsider)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->tenant->id]);
        $this->get($this->url())->assertNotFound();
        $this->post($this->url(), ['template_id' => $this->template->id])->assertNotFound();
    }

    public function test_substitutes_can_manage_but_cannot_change_unrelated_exams(): void
    {
        $exam = $this->assign();
        $substitute = User::factory()->create();
        $this->grantRole($substitute, $this->tenant, 'teacher');
        $this->session->teachers()->updateExistingPivot($this->teacher->id, ['substitute_id' => $substitute->id]);
        $this->actingAs($substitute)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->tenant->id]);
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page->where('context.can_manage', true));
        $this->patch(route('exams.context.publish', ['class-session', $this->session->id, $this->template]), ['is_published' => false])->assertNotFound();
        $this->patch(route('exams.context.publish', ['class-session', $this->session->id, $exam]), ['is_published' => false])->assertRedirect();
        $this->assertTrue($this->template->fresh()->is_published);
        $this->assertFalse($exam->fresh()->is_published);
    }

    public function test_drafts_and_invalid_template_ids_are_not_assignable(): void
    {
        $this->asTeacher();
        $this->post($this->url(), [])->assertSessionHasErrors('template_id');
        $this->post($this->url(), ['template_id' => 'invalid'])->assertSessionHasErrors('template_id');
        $this->template->update(['is_published' => false]);
        $this->post($this->url(), ['template_id' => $this->template->id])->assertSessionHasErrors('template_id');
        $this->assertSame(0, Exam::query()->whereNotNull('context_type')->count());
    }

    private function assign(?ClassSession $session = null): Exam
    {
        $session ??= $this->session;
        $this->asTeacher();
        $this->post($this->url($session), ['template_id' => $this->template->id])->assertRedirect($this->url($session));

        return Exam::query()->where('context_type', 'class-session')->where('context_id', $session->id)->sole();
    }

    private function url(?ClassSession $session = null): string
    {
        return route('exams.context', ['class-session', ($session ?? $this->session)->id]);
    }

    private function deleteUrl(Exam $exam): string
    {
        return route('exams.context.destroy', ['class-session', $this->session->id, $exam]);
    }

    private function asTeacher(): void
    {
        $this->actingAs($this->teacher)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->tenant->id]);
    }

    private function asLearner(): void
    {
        $this->actingAs($this->learner)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->tenant->id]);
    }
}

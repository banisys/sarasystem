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
use PHPUnit\Framework\Attributes\TestWith;
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

    private QuestionBank $bank;

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
        $this->bank = QuestionBank::factory()->for($this->tenant)->create(['owner_id' => $this->teacher->id]);
        Question::factory()->for($this->tenant)->descriptive()->create(['owner_id' => $this->teacher->id, 'score' => 4, 'model_answer' => 'راه‌حل نمونه'])->banks()->attach($this->bank);
        $this->template = Exam::factory()->for($this->tenant)->create(['max_attempts' => 1, 'total_score' => 20]);
        $this->template->sections()->create(['question_bank_id' => $this->bank->id, 'question_count' => 1]);
    }

    public function test_generating_exams_creates_independent_attempts_per_session(): void
    {
        $this->template->participantUsers()->attach($this->teacher);
        $first = $this->assign();
        $this->assertSame(1, Exam::query()->whereNotNull('context_type')->count());
        $this->assertSame(0, $first->participantUsers()->count());
        $this->assertCount(1, $first->question_snapshot);
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
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page->where('context.can_manage', false)->where('results', null)->has('banks', 0));
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

    public function test_the_author_can_delete_an_unused_exam_and_generate_another(): void
    {
        $exam = $this->assign();
        $delete = $this->deleteUrl($exam);
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page->where('exams.0.delete_url', $delete));

        $this->delete($delete)->assertRedirect($this->url())->assertSessionHas('status', 'آزمون از این جلسه حذف شد.');
        $this->assertModelMissing($exam);
        $this->assertModelExists($this->bank);
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

    public function test_a_session_manager_cannot_delete_another_users_exam(): void
    {
        $exam = $this->assign();
        $substitute = User::factory()->create();
        $this->grantRole($substitute, $this->tenant, 'teacher');
        $this->session->teachers()->updateExistingPivot($this->teacher->id, ['substitute_id' => $substitute->id]);
        $this->actingAs($substitute)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->tenant->id]);

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
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->patch(route('exams.context.publish', ['class-session', $this->session->id, $exam]), ['is_published' => false])->assertForbidden();
        $this->delete($this->deleteUrl($exam))->assertForbidden();
        $attempt = ExamAttempt::factory()->for($exam)->for($this->learner, 'user')->create();
        $this->get(route('exams.context.review', ['class-session', $this->session->id, $attempt]))->assertForbidden();
        $this->post(route('exams.context.grade', ['class-session', $this->session->id, $attempt]), ['scores' => []])->assertForbidden();
        $this->assertTrue($exam->fresh()->is_published);
    }

    public function test_foreign_sessions_banks_and_attempts_are_rejected(): void
    {
        $exam = $this->assign();
        $foreignTenant = Tenant::factory()->create();
        $foreignTemplate = Exam::factory()->for($foreignTenant)->create();
        $foreignSession = ClassSession::factory()->for(Classroom::factory()->for($foreignTenant))->taughtBy($this->teacher)->create();
        $foreignBank = QuestionBank::factory()->for($foreignTenant)->create(['owner_id' => $this->teacher->id, 'is_shared' => true]);
        $this->post($this->url(), $this->payload($foreignBank))->assertSessionHasErrors('banks.0.bank_id');
        $this->get($this->url($foreignSession))->assertNotFound();
        $this->post($this->url($foreignSession), $this->payload())->assertNotFound();
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
        $this->post($this->url(), $this->payload())->assertNotFound();
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

    public function test_generation_uses_only_accessible_active_questions_and_freezes_the_draw(): void
    {
        $colleague = User::factory()->create();
        $sharedBank = QuestionBank::factory()->for($this->tenant)->create(['owner_id' => $colleague->id, 'is_shared' => true]);
        $privateBank = QuestionBank::factory()->for($this->tenant)->create(['owner_id' => $colleague->id]);
        $shared = Question::factory()->for($this->tenant)->create(['owner_id' => $colleague->id, 'is_shared' => true]);
        $private = Question::factory()->for($this->tenant)->create(['owner_id' => $colleague->id]);
        $inactive = Question::factory()->for($this->tenant)->inactive()->create(['owner_id' => $colleague->id, 'is_shared' => true]);
        $sharedBank->questions()->attach([$shared->id, $private->id, $inactive->id]);

        $this->asTeacher();
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page
            ->has('banks', 2)->missing('templates'));
        $this->post($this->url(), $this->payload($privateBank))->assertSessionHasErrors('banks.0.bank_id');
        $this->post($this->url(), $this->payload($sharedBank, 2))->assertSessionHasErrors('banks.0.question_count');
        $this->assertSame(0, Exam::query()->whereNotNull('context_type')->count());

        $data = $this->payload();
        $data['banks'][] = ['bank_id' => $sharedBank->id, 'question_count' => 1];
        $this->post($this->url(), $data)->assertRedirect($this->url());
        $exam = Exam::query()->whereNotNull('context_type')->sole();
        $this->assertFalse($exam->is_published);
        $this->assertNull($exam->template_exam_id);
        $this->assertSame($shared->body, $exam->question_snapshot[1]['body']);
        $this->asLearner();
        $this->post(route('exams.attempt.start', $exam))->assertNotFound();

        $this->asTeacher();
        $this->patch(route('exams.context.publish', ['class-session', $this->session->id, $exam]), ['is_published' => true])->assertRedirect();
        $sharedBank->delete();
        $shared->delete();

        $this->asLearner();
        $this->post(route('exams.attempt.start', $exam))->assertRedirect(route('exams.show', $exam));
        $this->assertSame($shared->body, $exam->attempts()->sole()->answers()->get()[1]->question_body);
        $this->get(route('exams.show', $exam))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('exam.question_count', 2)->missing('exam.question_snapshot')->missing('attempt.questions.1.correct_option'));
    }

    public function test_legacy_template_selection_is_rejected(): void
    {
        $this->asTeacher();
        $this->post($this->url(), ['template_id' => $this->template->id])->assertSessionHasErrors('banks');
        $this->assertSame(0, Exam::query()->whereNotNull('context_type')->count());
    }

    public function test_question_picker_lists_only_eligible_questions_for_session_managers(): void
    {
        $colleague = User::factory()->create();
        $publicBank = QuestionBank::factory()->for($this->tenant)->create(['owner_id' => $colleague->id, 'is_shared' => true]);
        $privateBank = QuestionBank::factory()->for($this->tenant)->create(['owner_id' => $colleague->id]);
        $foreignBank = QuestionBank::factory()->for(Tenant::factory())->create(['owner_id' => $this->teacher->id, 'is_shared' => true]);
        $public = Question::factory()->for($this->tenant)->create(['owner_id' => $colleague->id, 'is_shared' => true]);
        $private = Question::factory()->for($this->tenant)->create(['owner_id' => $colleague->id]);
        $inactive = Question::factory()->for($this->tenant)->inactive()->create(['owner_id' => $colleague->id, 'is_shared' => true]);
        $publicBank->questions()->attach([$public->id, $private->id, $inactive->id]);

        $url = fn (QuestionBank $bank): string => route('exams.context.bank-questions', ['class-session', $this->session->id, $bank]);
        $this->asTeacher();
        $this->getJson($url($publicBank))->assertOk()->assertExactJson(['questions' => [[
            'id' => $public->id, 'body' => $public->body, 'type' => $public->type->getLabel(), 'score' => $public->score,
        ]]]);
        $this->getJson($url($privateBank))->assertNotFound();
        $this->getJson($url($foreignBank))->assertNotFound();
        $this->asLearner();
        $this->getJson($url($publicBank))->assertForbidden();
    }

    public function test_generation_keeps_manual_choices_and_reserves_them_before_the_random_draw(): void
    {
        $manual = $this->bank->questions()->sole();
        $random = Question::factory()->for($this->tenant)->create(['owner_id' => $this->teacher->id]);
        $this->bank->questions()->attach($random);
        $secondBank = QuestionBank::factory()->for($this->tenant)->create(['owner_id' => $this->teacher->id]);
        $secondBank->questions()->attach($manual);
        $data = $this->payload();
        $data['banks'][] = ['bank_id' => $secondBank->id, 'question_count' => 1, 'question_ids' => [$manual->id]];

        $this->asTeacher();
        $this->post($this->url(), $data)->assertRedirect($this->url());
        $exam = Exam::query()->whereNotNull('context_type')->sole();
        $this->assertSame([$random->body, $manual->body], array_column($exam->question_snapshot, 'body'));
    }

    public function test_generation_rejects_stale_or_unrelated_manual_questions_and_duplicate_choices(): void
    {
        $manual = $this->bank->questions()->sole();
        $other = Question::factory()->for($this->tenant)->create(['owner_id' => $this->teacher->id]);
        $secondBank = QuestionBank::factory()->for($this->tenant)->create(['owner_id' => $this->teacher->id]);
        $secondBank->questions()->attach([$manual->id, $other->id]);

        $this->asTeacher();
        $data = $this->payload();
        $data['banks'][0]['question_ids'] = [$other->id];
        $this->post($this->url(), $data)->assertSessionHasErrors('banks.0.question_ids');
        $data['banks'][0]['question_ids'] = [$manual->id];
        $duplicate = $data;
        $duplicate['banks'][] = ['bank_id' => $secondBank->id, 'question_count' => 1, 'question_ids' => [$manual->id]];
        $this->post($this->url(), $duplicate)->assertSessionHasErrors('banks.1.question_ids.0');
        $manual->update(['is_active' => false]);
        $this->post($this->url(), $data)->assertSessionHasErrors('banks.0.question_ids');
        $this->assertSame(0, Exam::query()->whereNotNull('context_type')->count());
    }

    #[TestWith(['ناظر'])]
    #[TestWith(['پشتیبان'])]
    public function test_non_attendee_class_staff_can_manage_a_personal_library(string $roleTitle): void
    {
        $staff = User::factory()->create();
        $this->grantRole($staff, $this->tenant, 'staff');
        $role = ClassRole::factory()->for($this->tenant)->create(['title' => $roleTitle, 'is_attendee' => false]);
        ClassMember::factory()->for($this->session->classroom)->for($staff, 'user')->for($role)
            ->create(['joined_on' => today()]);
        $this->actingAs($staff)->withSession([ResolveCurrentTenant::SESSION_KEY => $this->tenant->id]);

        $this->get(route('exams.library'))->assertInertia(fn (AssertableInertia $page) => $page->component('Exam::QuestionLibrary'));
        $this->post(route('exams.library.banks.store'), ['title' => 'بانک کلاس', 'is_shared' => false])->assertRedirect();
        $this->assertDatabaseHas('exam_question_banks', ['owner_id' => $staff->id, 'tenant_id' => $this->tenant->id]);
    }

    private function payload(?QuestionBank $bank = null, int $count = 1): array
    {
        return [
            'title' => 'تمرین مکالمه', 'duration_minutes' => 10, 'total_score' => 20, 'passing_score' => 12,
            'banks' => [['bank_id' => ($bank ?? $this->bank)->id, 'question_count' => $count]],
        ];
    }

    public function test_the_author_can_preview_and_reorder_a_draft_and_participants_receive_the_saved_order(): void
    {
        $exam = $this->generatePreviewDraft();
        $url = route('exams.context.preview', ['class-session', $this->session->id, $exam]);
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page->where('exams.0.preview_url', $url));
        $preview = $this->getJson($url)->assertOk()->assertJsonPath('can_reorder', true)->assertJsonCount(2, 'questions')->json();
        $this->assertArrayNotHasKey('model_answer', $preview['questions'][0]);
        $this->assertSame($exam->question_snapshot[0]['body'], $preview['questions'][0]['body']);
        $this->patchJson($url, ['order' => [0, 2], 'revision' => $preview['revision']])->assertUnprocessable()->assertJsonValidationErrors('order');

        $response = $this->patchJson($url, ['order' => [1, 0], 'revision' => $preview['revision']])->assertOk();
        $this->assertNotSame($preview['revision'], $response->json('revision'));
        $this->assertSame(array_reverse($exam->question_snapshot), $exam->fresh()->question_snapshot);
        $this->patchJson($url, ['order' => [1, 0], 'revision' => $preview['revision']])->assertUnprocessable()->assertJsonValidationErrors('order');

        $this->patch(route('exams.context.publish', ['class-session', $this->session->id, $exam]), ['is_published' => true])->assertRedirect();
        $this->getJson($url)->assertJsonPath('can_reorder', false);
        $this->patchJson($url, ['order' => [1, 0], 'revision' => $response->json('revision')])->assertUnprocessable();
        $this->asLearner();
        $this->post(route('exams.attempt.start', $exam))->assertRedirect();
        $this->assertSame(array_reverse(array_column($exam->question_snapshot, 'body')), $exam->attempts()->sole()->answers()->pluck('question_body')->all());
    }

    public function test_preview_is_scoped_to_the_session_and_only_the_author_can_reorder(): void
    {
        $exam = $this->generatePreviewDraft();
        $url = route('exams.context.preview', ['class-session', $this->session->id, $exam]);
        $revision = $this->getJson($url)->json('revision');
        $otherSession = ClassSession::factory()->for($this->session->classroom)->taughtBy($this->teacher)->create();
        $this->getJson(route('exams.context.preview', ['class-session', $otherSession->id, $exam]))->assertNotFound();

        $foreignExam = Exam::factory()->for(Tenant::factory())->create();
        $this->getJson(route('exams.context.preview', ['class-session', $this->session->id, $foreignExam]))->assertNotFound();

        $substitute = User::factory()->create();
        $this->grantRole($substitute, $this->tenant, 'teacher');
        $this->session->teachers()->updateExistingPivot($this->teacher->id, ['substitute_id' => $substitute->id]);
        $this->actingAs($substitute);
        $this->getJson($url)->assertOk()->assertJsonPath('can_reorder', false);
        $this->patchJson($url, ['order' => [1, 0], 'revision' => $revision])->assertForbidden();

        $this->asLearner();
        $this->getJson($url)->assertForbidden();
        $this->patchJson($url, ['order' => [1, 0], 'revision' => $revision])->assertForbidden();

        $this->asTeacher();
        ExamAttempt::factory()->for($exam)->for($this->learner, 'user')->create();
        $this->getJson($url)->assertJsonPath('can_reorder', false);
        $this->patchJson($url, ['order' => [1, 0], 'revision' => $revision])->assertUnprocessable();
        $this->assertSame($exam->question_snapshot, $exam->fresh()->question_snapshot);
    }

    private function generatePreviewDraft(): Exam
    {
        $this->bank->questions()->attach(Question::factory()->for($this->tenant)->create(['owner_id' => $this->teacher->id]));
        $this->asTeacher();
        $this->post($this->url(), $this->payload(count: 2))->assertRedirect($this->url());

        return Exam::query()->whereNotNull('question_snapshot')->sole();
    }

    public function test_session_exam_settings_are_saved_on_create_and_edit_without_changing_the_questions_or_session(): void
    {
        $this->asTeacher();
        $create = $this->payload() + [
            'description' => 'راهنمای آزمون', 'grace_minutes' => 7, 'questions_per_page' => 2,
            'negative_marking' => 3, 'max_attempts' => 2, 'retake_policy' => 'failed_only',
            'result_visibility' => 'score', 'shuffle_questions' => true, 'shuffle_options' => true,
        ];
        $this->post($this->url(), $create + ['is_published' => true, 'starts_at' => now()->addWeek()])->assertRedirect();
        $exam = Exam::query()->whereNotNull('question_snapshot')->sole();
        $this->assertFalse($exam->is_published);
        $this->assertTrue($exam->starts_at->equalTo($this->session->starts_at));
        $this->get($this->url())->assertInertia(fn (AssertableInertia $page) => $page
            ->where('exams.0.settings.description', 'راهنمای آزمون')
            ->where('exams.0.settings.grace_minutes', 7)
            ->where('exams.0.settings.questions_per_page', 2)
            ->where('exams.0.settings.negative_marking', 3)
            ->where('exams.0.settings.max_attempts', 2)
            ->where('exams.0.settings.retake_policy', 'failed_only')
            ->where('exams.0.settings.result_visibility', 'score')
            ->where('exams.0.settings.shuffle_questions', true)
            ->where('exams.0.settings.shuffle_options', true));

        $url = route('exams.context.settings.update', ['class-session', $this->session->id, $exam]);
        $edit = array_replace($create, [
            'title' => 'عنوان تازه', 'description' => null, 'negative_marking' => null,
            'shuffle_questions' => false, 'result_visibility' => 'pass_fail',
        ]);
        $this->patch($url, array_replace($edit, ['passing_score' => 21, 'grace_minutes' => 61, 'retake_policy' => 'unknown']))
            ->assertSessionHasErrors(['passing_score', 'grace_minutes', 'retake_policy']);
        $this->patch($url, $edit + ['context_id' => 999, 'question_snapshot' => []])->assertRedirect($this->url());
        $updated = $exam->fresh();
        $this->assertSame('عنوان تازه', $updated->title);
        $this->assertNull($updated->description);
        $this->assertNull($updated->negative_marking);
        $this->assertFalse($updated->shuffle_questions);
        $this->assertSame('pass_fail', $updated->result_visibility->value);
        $this->assertSame($exam->context_id, $updated->context_id);
        $this->assertSame($exam->question_snapshot, $updated->question_snapshot);
    }

    public function test_only_the_author_can_edit_settings_of_an_unused_draft_in_this_session(): void
    {
        $exam = $this->generatePreviewDraft();
        $url = route('exams.context.settings.update', ['class-session', $this->session->id, $exam]);
        $data = $this->payload();
        $otherSession = ClassSession::factory()->for($this->session->classroom)->taughtBy($this->teacher)->create();
        $this->patch(route('exams.context.settings.update', ['class-session', $otherSession->id, $exam]), $data)->assertNotFound();
        $foreign = Exam::factory()->for(Tenant::factory())->create();
        $this->patch(route('exams.context.settings.update', ['class-session', $this->session->id, $foreign]), $data)->assertNotFound();

        $substitute = User::factory()->create();
        $this->grantRole($substitute, $this->tenant, 'teacher');
        $this->session->teachers()->updateExistingPivot($this->teacher->id, ['substitute_id' => $substitute->id]);
        $this->actingAs($substitute)->patch($url, $data)->assertForbidden();
        $this->asLearner();
        $this->patch($url, $data)->assertForbidden();

        $this->asTeacher();
        $exam->update(['is_published' => true]);
        $this->patch($url, $data)->assertSessionHasErrors('settings');
        $exam->update(['is_published' => false]);
        ExamAttempt::factory()->for($exam)->for($this->learner, 'user')->create();
        $this->patch($url, $data)->assertSessionHasErrors('settings');
    }

    private function assign(?ClassSession $session = null): Exam
    {
        $session ??= $this->session;
        $this->asTeacher();
        $this->post($this->url($session), $this->payload())->assertRedirect($this->url($session));

        $exam = Exam::query()->where('context_type', 'class-session')->where('context_id', $session->id)->sole();
        $this->patch(route('exams.context.publish', ['class-session', $session->id, $exam]), ['is_published' => true])->assertRedirect();

        return $exam->refresh();
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

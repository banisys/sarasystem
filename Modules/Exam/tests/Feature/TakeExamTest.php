<?php

namespace Modules\Exam\Tests\Feature;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Modules\Exam\Enums\AttemptStatus;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Enums\RetakePolicy;
use Modules\Exam\Models\AttemptAnswer;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Services\ExamTaking;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TakeExamTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $student;

    private QuestionBank $bank;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->otherTenant] = Tenant::factory()->count(2)->create();

        $this->student = User::factory()->create();
        $this->grantRole($this->student, $this->tenant, 'student');

        $this->bank = QuestionBank::factory()->for($this->tenant)->create();
    }

    public function test_only_participants_see_a_published_exam(): void
    {
        $byName = $this->examFor($this->student);
        $byRole = Exam::factory()->for($this->tenant)->create(['title' => 'با نقش']);
        $byRole->participantRoles()->attach(Role::findByName('student', 'web'));
        $notMine = Exam::factory()->for($this->tenant)->create(['title' => 'دیگران']);
        $draft = $this->examFor($this->student, ['is_published' => false]);

        $this->visit(route('exams.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Exam::Index')
                ->has('exams', 2)
                ->where('navigation.0.title', 'آزمون‌ها'));

        $this->visit(route('exams.show', $byName))->assertOk();
        $this->visit(route('exams.show', $byRole))->assertOk();
        $this->visit(route('exams.show', $notMine))->assertNotFound();
        $this->visit(route('exams.show', $draft))->assertNotFound();
    }

    public function test_a_role_counts_only_in_the_exams_tenant(): void
    {
        $exam = Exam::factory()->for($this->otherTenant)->create();
        $exam->participantRoles()->attach(Role::findByName('student', 'web'));
        $this->grantRole($this->student, $this->otherTenant, 'parent');

        $this->visit(route('exams.show', $exam), $this->otherTenant)->assertNotFound();

        $this->grantRole($this->student, $this->otherTenant, 'student');

        $this->visit(route('exams.show', $exam), $this->otherTenant)->assertOk();
    }

    public function test_another_tenants_exam_is_not_found_even_for_its_participants(): void
    {
        $foreign = Exam::factory()->for($this->otherTenant)->create();
        $foreign->participantUsers()->attach($this->student);

        $this->visit(route('exams.show', $foreign))->assertNotFound();
        $this->post(route('exams.attempt.start', $foreign))->assertNotFound();

        $this->assertSame(0, ExamAttempt::withoutTenantScope()->count());
    }

    public function test_starting_draws_each_sections_share_of_active_questions_once(): void
    {
        $other = QuestionBank::factory()->for($this->tenant)->create();
        $shared = $this->questions(1, [$this->bank, $other])->first();
        $this->questions(2, [$this->bank]);
        Question::factory()->for($this->tenant)->inactive()->create()->banks()->attach($this->bank);
        $this->questions(1, [$other]);

        $exam = $this->examFor($this->student);
        $exam->sections()->createMany([
            ['question_bank_id' => $this->bank->id, 'question_count' => 10, 'sort' => 0],
            ['question_bank_id' => $other->id, 'question_count' => 2, 'sort' => 1],
        ]);

        $this->visit(route('exams.attempt.start', $exam), method: 'post')->assertRedirect(route('exams.show', $exam));

        $attempt = ExamAttempt::withoutTenantScope()->sole();
        $answers = AttemptAnswer::withoutTenantScope()->where('attempt_id', $attempt->id)->orderBy('sort')->get();

        // 3 active in the first bank (the shared one included), then 1 more from the second.
        $this->assertCount(4, $answers);
        $this->assertSame(1, $answers->where('question_id', $shared->id)->count());
        $this->assertSame($this->tenant->id, $attempt->tenant_id);
        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame('a', $answers->first()->correct_option);
        $this->assertSame(['key', 'text'], array_keys($answers->first()->options[0]));

        $this->visit(route('exams.show', $exam))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Exam::Show')
                ->has('attempt.questions', 4)
                ->missing('attempt.questions.0.options.0.is_correct'));

        // Starting again returns the running attempt.
        $this->visit(route('exams.attempt.start', $exam), method: 'post');
        $this->assertSame(1, ExamAttempt::withoutTenantScope()->count());
    }

    public function test_the_deadline_is_capped_by_the_exams_end(): void
    {
        $this->questions(1, [$this->bank]);
        $exam = $this->examWithBank(['duration_minutes' => 60, 'ends_at' => now()->addMinutes(10)]);

        $this->visit(route('exams.attempt.start', $exam), method: 'post');

        $this->assertTrue(ExamAttempt::withoutTenantScope()->sole()->deadline_at->equalTo($exam->ends_at));
    }

    public function test_answers_are_saved_and_submitted_with_negative_marking_and_written_answers_wait_for_review(): void
    {
        [$right, $wrong, $skipped] = $this->questions(3, [$this->bank], ['score' => 2]);
        $written = Question::factory()->for($this->tenant)->descriptive()->create(['score' => 4]);
        $written->banks()->attach($this->bank);
        $exam = $this->examWithBank(['negative_marking' => 2]);

        $this->visit(route('exams.attempt.start', $exam), method: 'post');
        $answers = AttemptAnswer::withoutTenantScope()->get()->keyBy('question_id');
        $foreignAnswer = $this->answerOfAnotherTenant();

        $this->visit(route('exams.attempt.save', $exam), method: 'put', data: ['answers' => [
            $answers[$right->id]->id => 'a',
            $answers[$wrong->id]->id => 'b',
            $answers[$skipped->id]->id => 'nope',
            $foreignAnswer->id => 'b',
        ]])->assertOk()->assertJsonStructure(['saved_at']);

        $this->assertSame('b', $answers[$wrong->id]->fresh()->selected_option);
        $this->assertNull($answers[$skipped->id]->fresh()->selected_option);
        $this->assertSame('a', $foreignAnswer->fresh()->selected_option);

        $this->visit(route('exams.attempt.submit', $exam), method: 'post', data: ['answers' => [
            $answers[$written->id]->id => 'پاسخ تشریحی',
        ]])->assertRedirect(route('exams.show', $exam));

        $attempt = ExamAttempt::withoutTenantScope()->whereBelongsTo($exam)->sole();
        $this->assertSame(AttemptStatus::PendingReview, $attempt->status);
        $this->assertNull($attempt->score);
        $this->assertSame(2.0, $answers[$right->id]->fresh()->score);
        $this->assertSame(-1.0, $answers[$wrong->id]->fresh()->score);
        $this->assertSame(0.0, $answers[$skipped->id]->fresh()->score);
        $this->assertNull($answers[$written->id]->fresh()->score);

        // Graded later: (2 - 1 + 0 + 3) of 10 raw → 8 of 20.
        $this->tenant->makeCurrent();
        $answers[$written->id]->fresh()->update(['score' => 3]);
        $attempt->recalculate();

        $this->assertSame(AttemptStatus::Completed, $attempt->status);
        $this->assertSame(8.0, $attempt->score);
        $this->assertFalse($attempt->passed());
    }

    public function test_fill_in_the_blank_answers_use_the_attempt_snapshot_and_stay_hidden_until_the_final_result(): void
    {
        $exam = $this->examWithBank(['max_attempts' => 2, 'negative_marking' => 3, 'result_visibility' => ResultVisibility::ScoreWithAnswers]);
        $questions = $this->questions(3, [$this->bank], [
            'type' => QuestionType::FillInTheBlank,
            'body' => 'حاصل ___ است.',
            'accepted_answers' => ['پنج', 'عدد ۵'],
            'score' => 2,
        ]);

        $this->visit(route('exams.attempt.start', $exam), method: 'post')->assertRedirect();
        $answers = $this->tenant->execute(fn () => ExamAttempt::query()->sole()->answers()->get());
        $this->visit(route('exams.show', $exam))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('attempt.questions.0.blank_parts', ['حاصل ', ' است.'])
            ->where('attempt.questions.0.options', [])
            ->missing('attempt.questions.0.accepted_answers'));

        $this->tenant->execute(fn () => $questions[0]->update(['accepted_answers' => ['هفت']]));
        $this->visit(route('exams.attempt.submit', $exam), method: 'post', data: ['answers' => [
            $answers[0]->id => 'عدد   ٥',
            $answers[1]->id => 'هشت',
        ]])->assertRedirect();

        $this->tenant->execute(fn () => $this->assertSame([2.0, 0.0, 0.0], ExamAttempt::query()->sole()->answers()->pluck('score')->all()));
        $this->visit(route('exams.show', $exam))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('result.is_graded', true)
            ->where('result.answers.0.correct_answer', null));

        $exam->update(['max_attempts' => 1]);
        $this->visit(route('exams.show', $exam))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('result.answers.0.correct_answer', 'پنج، عدد ۵'));
    }

    public function test_an_empty_written_answer_scores_zero_without_review(): void
    {
        Question::factory()->for($this->tenant)->descriptive()->create()->banks()->attach($this->bank);
        $exam = $this->examWithBank();

        $this->visit(route('exams.attempt.start', $exam), method: 'post');
        $this->visit(route('exams.attempt.submit', $exam), method: 'post', data: ['answers' => []]);

        $attempt = ExamAttempt::withoutTenantScope()->sole();
        $this->assertSame(AttemptStatus::Completed, $attempt->status);
        $this->assertSame(0.0, $attempt->score);
    }

    public function test_an_attempt_past_its_time_closes_with_its_saved_answers(): void
    {
        $question = $this->questions(1, [$this->bank])->first();
        $exam = $this->examWithBank(['duration_minutes' => 10, 'grace_minutes' => 2]);

        $this->visit(route('exams.attempt.start', $exam), method: 'post');
        $answer = AttemptAnswer::withoutTenantScope()->sole();
        $this->visit(route('exams.attempt.save', $exam), method: 'put', data: ['answers' => [$answer->id => 'a']]);

        // Within the grace time answers are still taken.
        $this->travel(11)->minutes();
        $this->visit(route('exams.attempt.save', $exam), method: 'put', data: ['answers' => [$answer->id => 'a']])->assertOk();

        $this->travel(2)->minutes();
        $this->visit(route('exams.attempt.save', $exam), method: 'put', data: ['answers' => [$answer->id => 'b']])
            ->assertSessionHasErrors('answers');

        $attempt = ExamAttempt::withoutTenantScope()->sole();
        $this->assertTrue($attempt->timed_out);
        $this->assertSame(AttemptStatus::Completed, $attempt->status);
        $this->assertSame(20.0, $attempt->score);
        $this->assertSame($question->id, $answer->question_id);
    }

    public function test_attempts_are_limited_and_failed_only_blocks_after_a_pass(): void
    {
        $this->questions(1, [$this->bank]);
        $exam = $this->examWithBank(['max_attempts' => 3, 'retake_policy' => RetakePolicy::FailedOnly]);

        $this->takeAndAnswer($exam, 'b');
        $this->visit(route('exams.show', $exam))->assertInertia(fn (AssertableInertia $page) => $page->where('blocked', null));

        $this->takeAndAnswer($exam, 'a');
        $this->visit(route('exams.show', $exam))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('blocked', 'شما در این آزمون قبول شده‌اید.'));
        $this->visit(route('exams.attempt.start', $exam), method: 'post')->assertSessionHasErrors('exam');

        $exam->update(['retake_policy' => RetakePolicy::Always, 'max_attempts' => 2]);
        $this->visit(route('exams.attempt.start', $exam), method: 'post')->assertSessionHasErrors('exam');
        $this->assertSame(2, ExamAttempt::withoutTenantScope()->count());
    }

    public function test_an_exam_can_only_be_started_within_its_window(): void
    {
        $this->questions(1, [$this->bank]);
        $exam = $this->examWithBank(['starts_at' => now()->addHour()]);

        $this->visit(route('exams.attempt.start', $exam), method: 'post')->assertSessionHasErrors('exam');

        $exam->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subHour()]);
        $this->visit(route('exams.attempt.start', $exam), method: 'post')->assertSessionHasErrors('exam');

        $this->assertSame(0, ExamAttempt::withoutTenantScope()->count());
    }

    public function test_the_result_shows_only_what_the_exam_allows(): void
    {
        $this->questions(1, [$this->bank]);
        $exam = $this->examWithBank(['result_visibility' => ResultVisibility::Hidden, 'max_attempts' => 2]);
        $this->takeAndAnswer($exam, 'a');

        $this->visit(route('exams.show', $exam))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('result.score', null)
            ->where('result.passed', null)
            ->where('result.answers', []));

        $exam->update(['result_visibility' => ResultVisibility::PassFail]);
        $this->visit(route('exams.show', $exam))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('result.score', null)
            ->where('result.passed', true));

        $exam->update(['result_visibility' => ResultVisibility::ScoreWithAnswers]);
        $this->visit(route('exams.show', $exam))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('result.score', 20)
            ->where('result.answers.0.your_answer', 'گزینه a')
            ->where('result.answers.0.correct_answer', 'گزینه a'));
    }

    public function test_overdue_attempts_are_closed_across_batches_without_touching_other_tenants_or_grace_time(): void
    {
        $this->freezeTime();
        $exam = $this->examFor($this->student, ['grace_minutes' => 2]);
        $this->tenant->makeCurrent();
        ExamAttempt::factory()->for($exam)->for($this->student)->count(101)
            ->create(['deadline_at' => now()->subMinutes(3)]);
        $withinGrace = ExamAttempt::factory()->for($exam)->for($this->student)
            ->create(['deadline_at' => now()->subMinute()]);
        $foreign = ExamAttempt::factory()->for(Exam::factory()->for($this->otherTenant))->for($this->student)
            ->create(['deadline_at' => now()->subHour()]);

        app(ExamTaking::class)->closeOverdueAttempts();

        $this->assertSame(101, $exam->attempts()->finished()->where('timed_out', true)->count());
        $this->assertSame(AttemptStatus::InProgress, $withinGrace->fresh()->status);
        $this->assertSame(AttemptStatus::InProgress, $foreign->fresh()->status);
    }

    public function test_the_exam_list_uses_each_participants_latest_attempt(): void
    {
        $first = $this->examFor($this->student, ['starts_at' => null]);
        $second = $this->examFor($this->student, ['starts_at' => null]);
        foreach ([$first, $second] as $exam) {
            ExamAttempt::factory()->for($exam)->for($this->student)->create(['status' => AttemptStatus::Completed]);
            ExamAttempt::factory()->for($exam)->for($this->student)->create(['status' => AttemptStatus::PendingReview]);
            ExamAttempt::factory()->for($exam)->create();
        }

        $this->visit(route('exams.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('exams', 2)
            ->where('exams.0.id', $second->id)
            ->where('exams.0.last_status', AttemptStatus::PendingReview->getLabel())
            ->where('exams.1.id', $first->id)
            ->where('exams.1.last_status', AttemptStatus::PendingReview->getLabel()));
    }

    public function test_a_stale_autosave_cannot_change_a_submitted_answer(): void
    {
        $this->questions(1, [$this->bank]);
        $exam = $this->examWithBank();
        $this->tenant->makeCurrent();
        $taking = app(ExamTaking::class);
        $attempt = $taking->start($exam, $this->student);
        $stale = $attempt->fresh();
        $answer = $attempt->answers()->sole();
        $taking->submit($attempt, [$answer->id => 'a']);

        try {
            $taking->save($stale, [$answer->id => 'b']);
            $this->fail('A submitted attempt accepted a stale autosave.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('answers', $exception->errors());
        }

        $this->assertSame('a', $answer->fresh()->selected_option);
        $this->assertSame(20.0, $attempt->fresh()->score);
    }

    public function test_finishing_twice_keeps_the_original_result_and_submission_time(): void
    {
        $this->freezeTime();
        $this->questions(1, [$this->bank]);
        $exam = $this->examWithBank();
        $this->tenant->makeCurrent();
        $taking = app(ExamTaking::class);
        $attempt = $taking->start($exam, $this->student);
        $stale = $attempt->fresh();
        $taking->submit($attempt, [$attempt->answers()->sole()->id => 'a']);
        $submittedAt = $attempt->submitted_at->toIso8601String();
        $exam->update(['total_score' => 100]);
        $this->travel(1)->minutes();

        $taking->finish($stale, timedOut: true);

        $this->assertSame(20.0, $stale->score);
        $this->assertFalse($stale->timed_out);
        $this->assertSame($submittedAt, $stale->submitted_at->toIso8601String());
    }

    public function test_start_uses_the_current_exam_settings_instead_of_a_stale_model(): void
    {
        $this->questions(1, [$this->bank]);
        $exam = $this->examWithBank();
        $this->tenant->makeCurrent();
        $exam->fresh()->update(['starts_at' => now()->addHour()]);

        try {
            app(ExamTaking::class)->start($exam, $this->student);
            $this->fail('An exam started before its updated start time.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('exam', $exception->errors());
        }

        $this->assertSame(0, $exam->attempts()->count());
    }

    public function test_finishing_an_attempt_from_another_tenant_is_rejected(): void
    {
        $answer = $this->answerOfAnotherTenant();
        $this->otherTenant->makeCurrent();
        $attempt = $answer->attempt;
        $this->tenant->makeCurrent();

        $this->expectException(ModelNotFoundException::class);

        app(ExamTaking::class)->finish($attempt, timedOut: true);
    }

    public function test_question_selection_loads_only_the_requested_share(): void
    {
        $this->questions(12, [$this->bank]);
        $exam = $this->examWithBank(['shuffle_questions' => true]);
        $exam->sections()->first()->update(['question_count' => 3]);
        $this->tenant->makeCurrent();
        $retrieved = 0;
        Question::retrieved(function () use (&$retrieved): void {
            $retrieved++;
        });

        $attempt = app(ExamTaking::class)->start($exam, $this->student);

        $this->assertSame(3, $attempt->answers()->count());
        $this->assertSame(3, $retrieved);
    }

    /**
     * A request as the student, with the tenant chosen on the web side.
     *
     * @param  array<string, mixed>  $data
     */
    private function visit(string $url, ?Tenant $tenant = null, string $method = 'get', array $data = []): TestResponse
    {
        return $this->actingAs($this->student)
            ->withSession([ResolveCurrentTenant::SESSION_KEY => ($tenant ?? $this->tenant)->id])
            ->{$method}($url, $data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function examFor(User $user, array $attributes = []): Exam
    {
        $exam = Exam::factory()->for($this->tenant)->create($attributes);
        $exam->participantUsers()->attach($user);

        return $exam;
    }

    /**
     * An exam for the student drawing up to 50 questions from the bank.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function examWithBank(array $attributes = []): Exam
    {
        $exam = $this->examFor($this->student, $attributes);
        $exam->sections()->create(['question_bank_id' => $this->bank->id, 'question_count' => 50]);

        return $exam;
    }

    /**
     * @param  list<QuestionBank>  $banks
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, Question>
     */
    private function questions(int $count, array $banks, array $attributes = []): Collection
    {
        return Question::factory()->for($this->tenant)->count($count)->create($attributes)
            ->each(fn (Question $question) => $question->banks()->attach(collect($banks)->pluck('id')));
    }

    private function takeAndAnswer(Exam $exam, string $option): void
    {
        $this->visit(route('exams.attempt.start', $exam), method: 'post');
        $answer = AttemptAnswer::withoutTenantScope()->latest('id')->first();
        $this->visit(route('exams.attempt.submit', $exam), method: 'post', data: ['answers' => [$answer->id => $option]]);
    }

    private function answerOfAnotherTenant(): AttemptAnswer
    {
        $attempt = ExamAttempt::factory()->for(Exam::factory()->for($this->otherTenant))->for($this->student)->create();

        return AttemptAnswer::withoutTenantScope()->create([
            'tenant_id' => $this->otherTenant->id,
            'attempt_id' => $attempt->id,
            'sort' => 0,
            'question_type' => 'choice',
            'question_body' => 'سؤال دیگر',
            'options' => [['key' => 'a', 'text' => 'الف'], ['key' => 'b', 'text' => 'ب']],
            'correct_option' => 'a',
            'max_score' => 1,
            'selected_option' => 'a',
        ]);
    }
}

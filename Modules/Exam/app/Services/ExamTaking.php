<?php

namespace Modules\Exam\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Enums\AttemptStatus;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Enums\RetakePolicy;
use Modules\Exam\Models\AttemptAnswer;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Models\Question;

/**
 * Taking an exam (ADR 0013): starting an attempt, saving answers while it
 * runs, submitting it (or closing it once its time is over) and grading.
 * Callers check first that the user is a participant of a published exam.
 */
class ExamTaking
{
    public const BLOCKED_NOT_STARTED = 'not_started';

    public const BLOCKED_ENDED = 'ended';

    public const BLOCKED_NO_ATTEMPTS_LEFT = 'no_attempts_left';

    public const BLOCKED_PASSED = 'passed';

    public const BLOCKED_PENDING_REVIEW = 'pending_review';

    /**
     * The user's running attempt, after closing it if its time is over.
     */
    public function currentAttempt(Exam $exam, User $user): ?ExamAttempt
    {
        $attempt = $exam->attempts()->whereBelongsTo($user)->inProgress()->latest('id')->first();

        if ($attempt !== null && $attempt->isOverdue()) {
            $this->finish($attempt, timedOut: true);

            return null;
        }

        return $attempt;
    }

    /**
     * @return Collection<int, ExamAttempt> newest first
     */
    public function finishedAttempts(Exam $exam, User $user): Collection
    {
        return $exam->attempts()->whereBelongsTo($user)->finished()->latest('id')->get();
    }

    /**
     * Why the user can't start a new attempt now (a BLOCKED_* reason), or
     * null if they can.
     */
    public function blockedReason(Exam $exam, User $user): ?string
    {
        if (! $exam->hasStarted()) {
            return self::BLOCKED_NOT_STARTED;
        }

        if ($exam->hasEnded()) {
            return self::BLOCKED_ENDED;
        }

        $finished = $this->finishedAttempts($exam, $user);

        if ($finished->count() >= $exam->max_attempts) {
            return self::BLOCKED_NO_ATTEMPTS_LEFT;
        }

        $latest = $finished->first();

        if ($latest !== null && $exam->retake_policy === RetakePolicy::FailedOnly) {
            if ($latest->status === AttemptStatus::PendingReview) {
                return self::BLOCKED_PENDING_REVIEW;
            }

            if ($latest->passed()) {
                return self::BLOCKED_PASSED;
            }
        }

        return null;
    }

    /**
     * Start an attempt (or return the running one): draw the questions and
     * copy them into the attempt's answers.
     *
     * @throws ValidationException when the user may not start one or the exam has no questions
     */
    public function start(Exam $exam, User $user): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $user): ExamAttempt {
            // One start at a time per exam, so a double click can't open two attempts.
            Exam::query()->whereKey($exam->getKey())->lockForUpdate()->first();

            if ($attempt = $this->currentAttempt($exam, $user)) {
                return $attempt;
            }

            if ($reason = $this->blockedReason($exam, $user)) {
                throw ValidationException::withMessages(['exam' => self::blockedMessage($reason)]);
            }

            $questions = $this->drawQuestions($exam);

            if ($questions->isEmpty()) {
                throw ValidationException::withMessages(['exam' => 'این آزمون هنوز سؤالی ندارد.']);
            }

            $deadline = now()->addMinutes($exam->duration_minutes);

            $attempt = $exam->attempts()->create([
                'tenant_id' => $exam->tenant_id,
                'user_id' => $user->getKey(),
                'status' => AttemptStatus::InProgress,
                'started_at' => now(),
                'deadline_at' => $exam->ends_at !== null && $exam->ends_at->lessThan($deadline) ? $exam->ends_at : $deadline,
            ]);

            $questions->values()->each(fn (Question $question, int $index) => $attempt->answers()->create([
                'tenant_id' => $exam->tenant_id,
                'question_id' => $question->getKey(),
                'sort' => $index,
                'question_type' => $question->type,
                'question_body' => $question->body,
                'options' => $question->type === QuestionType::Choice ? $this->servedOptions($question, (bool) $exam->shuffle_options) : null,
                'correct_option' => $question->type === QuestionType::Choice ? $question->correctOptionKey() : null,
                'max_score' => $question->score,
            ]));

            return $attempt;
        });
    }

    /**
     * Store the given answers of a running attempt, keyed by answer id: an
     * option key for a choice question, text for a written one; null or ''
     * clears. Unknown ids and option keys are ignored.
     *
     * @param  array<int|string, string|null>  $answers
     *
     * @throws ValidationException when the attempt is closed or its time is over
     */
    public function save(ExamAttempt $attempt, array $answers): void
    {
        if (! $attempt->isInProgress() || $attempt->isOverdue()) {
            throw ValidationException::withMessages(['answers' => 'زمان این آزمون تمام شده است.']);
        }

        $attempt->answers()->whereIn('id', array_keys($answers))->get()
            ->each(function (AttemptAnswer $answer) use ($answers): void {
                $value = filled($answers[$answer->getKey()]) ? (string) $answers[$answer->getKey()] : null;

                if ($answer->isChoice()) {
                    $answer->selected_option = $answer->optionText($value) !== null ? $value : null;
                } else {
                    $answer->text_answer = $value;
                }

                $answer->save();
            });
    }

    /**
     * Save the last answers and close the attempt.
     *
     * @param  array<int|string, string|null>  $answers
     */
    public function submit(ExamAttempt $attempt, array $answers): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $answers): ExamAttempt {
            $this->save($attempt, $answers);

            return $this->finish($attempt, timedOut: false);
        });
    }

    /**
     * Close the attempt with the answers saved so far: grade choice answers
     * and empty written ones, then sum the score.
     */
    public function finish(ExamAttempt $attempt, bool $timedOut): ExamAttempt
    {
        $exam = $attempt->exam;

        $attempt->answers()->get()->each(function (AttemptAnswer $answer) use ($exam): void {
            if ($answer->isChoice()) {
                $answer->score = $answer->choiceScore($exam->negative_marking);
            } elseif (blank($answer->text_answer)) {
                $answer->score = 0;
            }

            $answer->save();
        });

        $attempt->forceFill(['submitted_at' => now(), 'timed_out' => $timedOut]);
        $attempt->recalculate();

        return $attempt;
    }

    /**
     * Close every running attempt of the current tenant whose time is over.
     */
    public function closeOverdueAttempts(): void
    {
        ExamAttempt::query()->inProgress()->where('deadline_at', '<', now())->with('exam')->get()
            ->filter(fn (ExamAttempt $attempt): bool => $attempt->isOverdue())
            ->each(fn (ExamAttempt $attempt) => $this->finish($attempt, timedOut: true));
    }

    public static function blockedMessage(string $reason): string
    {
        return match ($reason) {
            self::BLOCKED_NOT_STARTED => 'زمان این آزمون هنوز شروع نشده است.',
            self::BLOCKED_ENDED => 'زمان این آزمون تمام شده است.',
            self::BLOCKED_NO_ATTEMPTS_LEFT => 'تعداد دفعات مجاز شرکت در این آزمون تمام شده است.',
            self::BLOCKED_PASSED => 'شما در این آزمون قبول شده‌اید.',
            self::BLOCKED_PENDING_REVIEW => 'پاسخ‌های شما در انتظار تصحیح است.',
            default => 'شرکت در این آزمون ممکن نیست.',
        };
    }

    /**
     * Each section's share of active questions of its bank, in section
     * order; a question already drawn by an earlier section is skipped.
     *
     * @return SupportCollection<int, Question>
     */
    private function drawQuestions(Exam $exam): SupportCollection
    {
        $drawn = collect();

        foreach ($exam->sections()->with('questionBank')->get() as $section) {
            $candidates = $section->questionBank?->questions()->active()->orderBy('exam_questions.id')->get()
                ->reject(fn (Question $question): bool => $drawn->has($question->getKey())) ?? collect();

            if ($exam->shuffle_questions) {
                $candidates = $candidates->shuffle();
            }

            $candidates->take($section->question_count)->each(fn (Question $question) => $drawn->put($question->getKey(), $question));
        }

        return $drawn->values();
    }

    /**
     * @return list<array{key: string, text: string}>
     */
    private function servedOptions(Question $question, bool $shuffle): array
    {
        $options = collect($question->options ?? [])->map(fn (array $option): array => ['key' => $option['key'], 'text' => $option['text']]);

        return ($shuffle ? $options->shuffle() : $options)->values()->all();
    }
}

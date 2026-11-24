<?php

namespace Modules\Exam\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Http\Controllers\Concerns\ChecksParticipant;
use Modules\Exam\Models\AttemptAnswer;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Models\Question;
use Modules\Exam\Services\ExamPerformance;
use Modules\Exam\Services\ExamTaking;
use Modules\Exam\Support\ExamContexts;

/**
 * The participant's side of exams (ADR 0013): their exams in the current
 * tenant, and one exam's page (start, take, result).
 */
class ExamController extends Controller
{
    use ChecksParticipant;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $exams = Exam::query()->published()->forParticipant($user)
            ->with(['attempts' => fn ($query) => $query->whereBelongsTo($user)->latest('id')->limit(1)])
            ->orderByRaw('starts_at desc nulls last')->orderByDesc('id')
            ->get()
            ->map(fn (Exam $exam): array => [
                'id' => $exam->id,
                'title' => $exam->title,
                'is_practice' => $exam->is_practice,
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'ends_at' => $exam->participationEndsAt()?->toIso8601String(),
                'allow_late_start' => $exam->allow_late_start,
                'duration_minutes' => $exam->duration_minutes,
                'is_open' => $exam->hasStarted() && ! $exam->hasEnded(),
                'last_status' => $exam->attempts->first()?->status->getLabel(),
            ]);

        return Inertia::render('Exam::Index', ['exams' => $exams]);
    }

    public function performance(Request $request, ExamPerformance $performance): Response
    {
        return Inertia::render('Exam::Performance', $performance->report($request->user(), $request->query('group')));
    }

    public function show(Request $request, Exam $exam, ExamTaking $taking): Response
    {
        $user = $request->user();
        $this->ensureParticipant($exam, $user);

        $attempt = $taking->currentAttempt($exam, $user);
        $finished = $taking->finishedAttempts($exam, $user);
        $latest = $attempt === null ? $finished->first() : null;
        $blockedReason = $attempt === null ? $taking->blockedReason($exam, $user) : null;
        $retakesClosed = in_array($blockedReason, [ExamTaking::BLOCKED_ENDED, ExamTaking::BLOCKED_NO_ATTEMPTS_LEFT, ExamTaking::BLOCKED_PASSED], true);
        $exam->load('sections');
        $context = $exam->context_type === null ? null : app(ExamContexts::class)->resolve($exam->context_type, $exam->context_id, $user);

        return Inertia::render('Exam::Show', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'is_practice' => $exam->is_practice,
                'description' => $exam->description,
                'context_url' => $exam->context_type === null ? null : app(ExamContexts::class)->url($exam->context_type, $exam->context_id),
                'context_label' => $context?->hostLabel,
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'ends_at' => $exam->participationEndsAt()?->toIso8601String(),
                'allow_late_start' => $exam->allow_late_start,
                'duration_minutes' => $exam->duration_minutes,
                'total_score' => $exam->is_practice ? null : $exam->total_score,
                'passing_score' => $exam->is_practice ? null : $exam->passing_score,
                'question_count' => $exam->questionCount(),
                'questions_per_page' => $exam->questions_per_page,
                'max_attempts' => $exam->is_practice ? null : $exam->max_attempts,
                'negative_marking' => $exam->is_practice ? null : $exam->negative_marking,
            ],
            'attemptsUsed' => $finished->count(),
            'blocked' => $blockedReason === null ? null : ExamTaking::blockedMessage($blockedReason),
            'attempt' => $attempt === null ? null : $this->runningAttempt($exam, $attempt),
            'result' => $latest === null ? null : $this->result($exam, $latest, $retakesClosed),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function runningAttempt(Exam $exam, ExamAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'deadline_at' => $attempt->deadline_at->toIso8601String(),
            'seconds_left' => max(0, now()->diffInSeconds($attempt->deadline_at, false)),
            'questions' => $attempt->answers()->get()->map(fn (AttemptAnswer $answer): array => [
                'id' => $answer->id,
                'type' => $answer->question_type->value,
                'body' => $answer->question_body,
                'blank_parts' => $answer->isFillInTheBlank() ? explode(Question::BLANK_MARKER, $answer->question_body, 2) : null,
                'options' => $answer->options ?? [],
                'max_score' => $exam->is_practice ? null : $answer->max_score,
                'solution_url' => $exam->is_practice ? route('exams.attempt.solution', [$exam, $answer]) : null,
                'answer' => $answer->isChoice() ? $answer->selected_option : $answer->text_answer,
            ])->all(),
        ];
    }

    /**
     * What the participant may see of their last attempt, per the exam's
     * result_visibility.
     *
     * @return array<string, mixed>
     */
    private function result(Exam $exam, ExamAttempt $attempt, bool $retakeBlocked): array
    {
        $visibility = $exam->result_visibility;
        $isGraded = $attempt->score !== null;

        return [
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'timed_out' => $attempt->timed_out,
            'status' => $attempt->status->getLabel(),
            'is_graded' => $isGraded,
            'passed' => $visibility->showsPassed() && $isGraded ? $attempt->passed() : null,
            'score' => $visibility->showsScore() ? $attempt->score : null,
            'answers' => $exam->is_practice || $visibility === ResultVisibility::ScoreWithAnswers
                ? $attempt->answers()->get()->map(fn (AttemptAnswer $answer): array => [
                    'id' => $answer->id,
                    'body' => $answer->question_body,
                    'type' => $answer->question_type->value,
                    'your_answer' => $answer->isChoice() ? $answer->optionText($answer->selected_option) : $answer->text_answer,
                    'correct_answer' => ! $exam->is_practice && ($answer->isChoice() || $retakeBlocked) ? $answer->correctAnswerText() : null,
                    'solution_url' => $exam->is_practice ? route('exams.attempt.solution', [$exam, $answer]) : null,
                    'score' => $answer->score,
                    'max_score' => $exam->is_practice ? null : $answer->max_score,
                ])->all()
                : [],
        ];
    }
}

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
use Modules\Exam\Services\ExamTaking;

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
            ->with(['attempts' => fn ($query) => $query->whereBelongsTo($user)->latest('id')])
            ->orderByRaw('starts_at desc nulls last')->orderByDesc('id')
            ->get()
            ->map(fn (Exam $exam): array => [
                'id' => $exam->id,
                'title' => $exam->title,
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'ends_at' => $exam->ends_at?->toIso8601String(),
                'duration_minutes' => $exam->duration_minutes,
                'is_open' => $exam->hasStarted() && ! $exam->hasEnded(),
                'last_status' => $exam->attempts->first()?->status->getLabel(),
            ]);

        return Inertia::render('Exam::Index', ['exams' => $exams]);
    }

    public function show(Request $request, Exam $exam, ExamTaking $taking): Response
    {
        $user = $request->user();
        $this->ensureParticipant($exam, $user);

        $attempt = $taking->currentAttempt($exam, $user);
        $finished = $taking->finishedAttempts($exam, $user);
        $blockedReason = $attempt === null ? $taking->blockedReason($exam, $user) : null;
        $exam->load('sections');

        return Inertia::render('Exam::Show', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'ends_at' => $exam->ends_at?->toIso8601String(),
                'duration_minutes' => $exam->duration_minutes,
                'total_score' => $exam->total_score,
                'passing_score' => $exam->passing_score,
                'question_count' => $exam->questionCount(),
                'questions_per_page' => $exam->questions_per_page,
                'max_attempts' => $exam->max_attempts,
                'negative_marking' => $exam->negative_marking,
            ],
            'attemptsUsed' => $finished->count(),
            'blocked' => $blockedReason === null ? null : ExamTaking::blockedMessage($blockedReason),
            'attempt' => $attempt === null ? null : $this->runningAttempt($attempt),
            'result' => $attempt === null && $finished->isNotEmpty() ? $this->result($exam, $finished->first()) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function runningAttempt(ExamAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'deadline_at' => $attempt->deadline_at->toIso8601String(),
            'seconds_left' => max(0, now()->diffInSeconds($attempt->deadline_at, false)),
            'questions' => $attempt->answers()->get()->map(fn (AttemptAnswer $answer): array => [
                'id' => $answer->id,
                'type' => $answer->question_type->value,
                'body' => $answer->question_body,
                'options' => $answer->options ?? [],
                'max_score' => $answer->max_score,
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
    private function result(Exam $exam, ExamAttempt $attempt): array
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
            'answers' => $visibility === ResultVisibility::ScoreWithAnswers
                ? $attempt->answers()->get()->map(fn (AttemptAnswer $answer): array => [
                    'id' => $answer->id,
                    'body' => $answer->question_body,
                    'type' => $answer->question_type->value,
                    'your_answer' => $answer->isChoice() ? $answer->optionText($answer->selected_option) : $answer->text_answer,
                    'correct_answer' => $answer->isChoice() ? $answer->optionText($answer->correct_option) : null,
                    'score' => $answer->score,
                    'max_score' => $answer->max_score,
                ])->all()
                : [],
        ];
    }
}

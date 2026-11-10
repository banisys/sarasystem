<?php

namespace Modules\Exam\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Exam\Models\AttemptAnswer;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Services\ContextExams;
use Modules\Exam\Services\ExamTaking;
use Modules\Exam\Support\ExamContext;
use Modules\Exam\Support\ExamContexts;

class ContextExamController extends Controller
{
    public function show(Request $request, string $type, int $context, ExamContexts $contexts, ContextExams $exams): Response
    {
        $host = $this->context($request, $type, $context, $contexts);
        $query = $exams->query($host);
        if (! $host->canManage) {
            $query->published()->forParticipant($request->user());
        }

        $records = $query->withCount('attempts')->latest('id')->get();
        $results = $host->canManage
            ? ExamAttempt::query()->whereIn('exam_id', $exams->query($host)->select('id'))
                ->with(['user', 'exam'])->latest('id')->paginate(25)->through(fn (ExamAttempt $attempt): array => [
                    'id' => $attempt->id,
                    'name' => $attempt->user->name,
                    'exam' => $attempt->exam->title,
                    'status' => $attempt->status->getLabel(),
                    'score' => $attempt->score,
                    'total_score' => $attempt->exam->total_score,
                    'url' => route('exams.context.review', [$type, $context, $attempt]),
                ])
            : null;

        return Inertia::render('Exam::Context', [
            'context' => ['type' => $type, 'id' => $context, 'title' => $host->title, 'back_url' => $host->backUrl, 'can_manage' => $host->canManage],
            'templates' => $host->canManage ? Exam::query()->published()->whereNull('context_type')->orderBy('title')->get(['id', 'title']) : [],
            'exams' => $records->map(fn (Exam $exam): array => [
                'id' => $exam->id, 'title' => $exam->title, 'is_published' => $exam->is_published,
                'starts_at' => $exam->starts_at?->toIso8601String(), 'ends_at' => $exam->ends_at?->toIso8601String(),
                'attempts_count' => $host->canManage ? $exam->attempts_count : null,
                'take_url' => $host->canParticipate && $exam->is_published ? route('exams.show', $exam) : null,
            ]),
            'results' => $results,
        ]);
    }

    public function store(Request $request, string $type, int $context, ExamContexts $contexts, ContextExams $exams): RedirectResponse
    {
        $host = $this->context($request, $type, $context, $contexts, manage: true);
        $data = $request->validate(['template_id' => ['required', 'integer']]);
        $exams->assign($host, (int) $data['template_id']);

        return to_route('exams.context', [$type, $context])->with('status', 'آزمون مستقل جلسه آماده شد.');
    }

    public function publish(Request $request, string $type, int $context, Exam $exam, ExamContexts $contexts, ContextExams $exams): RedirectResponse
    {
        $host = $this->context($request, $type, $context, $contexts, manage: true);
        abort_unless($exams->query($host)->whereKey($exam->id)->exists(), 404);
        $data = $request->validate(['is_published' => ['required', 'boolean']]);
        $exam->update(['is_published' => $data['is_published']]);

        return to_route('exams.context', [$type, $context])->with('status', 'وضعیت انتشار آزمون تغییر کرد.');
    }

    public function review(Request $request, string $type, int $context, ExamAttempt $attempt, ExamContexts $contexts, ContextExams $exams, ExamTaking $taking): Response
    {
        $host = $this->context($request, $type, $context, $contexts, manage: true);
        $this->ensureAttempt($host, $attempt, $exams);
        if ($attempt->isInProgress() && $attempt->isOverdue()) {
            $taking->finish($attempt, timedOut: true);
        }

        return Inertia::render('Exam::Review', [
            'title' => $attempt->exam->title,
            'name' => $attempt->user->name,
            'status' => $attempt->status->getLabel(),
            'can_grade' => ! $attempt->isInProgress(),
            'back_url' => $contexts->url($type, $context),
            'save_url' => route('exams.context.grade', [$type, $context, $attempt]),
            'answers' => $attempt->answers()->get()->map(fn (AttemptAnswer $answer): array => [
                'id' => $answer->id, 'body' => $answer->question_body,
                'answer' => $answer->isChoice() ? $answer->optionText($answer->selected_option) : $answer->text_answer,
                'correct' => $answer->isChoice() ? $answer->optionText($answer->correct_option) : null,
                'is_choice' => $answer->isChoice(), 'max_score' => $answer->max_score, 'score' => $answer->score,
            ]),
        ]);
    }

    public function grade(Request $request, string $type, int $context, ExamAttempt $attempt, ExamContexts $contexts, ContextExams $exams): RedirectResponse
    {
        $host = $this->context($request, $type, $context, $contexts, manage: true);
        $this->ensureAttempt($host, $attempt, $exams);
        $data = $request->validate(['scores' => ['required', 'array'], 'scores.*' => ['nullable', 'numeric', 'min:0']]);

        DB::transaction(function () use ($attempt, $data, $request): void {
            $locked = ExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->isInProgress()) {
                throw ValidationException::withMessages(['scores' => 'آزمون هنوز تمام نشده است.']);
            }
            $answers = $locked->answers()->get()->keyBy('id');
            foreach ($data['scores'] as $id => $score) {
                $answer = $answers->get($id);
                if ($answer === null || $answer->isChoice() || ($score !== null && $score > $answer->max_score)) {
                    throw ValidationException::withMessages(['scores' => 'فقط نمرهٔ پاسخ تشریحی همین آزمون، تا سقف نمرهٔ سؤال، قابل ثبت است.']);
                }
                $score = $score === null ? null : (float) $score;
                if ($answer->score !== $score) {
                    $answer->update(['score' => $score, 'graded_by' => $score === null ? null : $request->user()->id, 'graded_at' => $score === null ? null : now()]);
                }
            }
            $locked->recalculate();
        });

        return to_route('exams.context.review', [$type, $context, $attempt])->with('status', 'نمره‌ها ثبت شد.');
    }

    private function context(Request $request, string $type, int $id, ExamContexts $contexts, bool $manage = false): ExamContext
    {
        $context = $contexts->resolve($type, $id, $request->user());
        abort_if($context === null, 404);
        abort_if($manage && ! $context->canManage, 403);

        return $context;
    }

    private function ensureAttempt(ExamContext $context, ExamAttempt $attempt, ContextExams $exams): void
    {
        abort_unless($exams->query($context)->whereKey($attempt->exam_id)->exists(), 404);
    }
}

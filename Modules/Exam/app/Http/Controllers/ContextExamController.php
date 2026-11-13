<?php

namespace Modules\Exam\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Exam\Enums\QuestionType;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Enums\RetakePolicy;
use Modules\Exam\Models\AttemptAnswer;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\ExamAttempt;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Services\ContextExams;
use Modules\Exam\Services\ExamTaking;
use Modules\Exam\Support\ExamContext;
use Modules\Exam\Support\ExamContexts;
use Modules\Exam\Support\QuestionLibrary;
use Modules\Exam\Support\SessionExamSettings;

class ContextExamController extends Controller
{
    public function show(Request $request, string $type, int $context, ExamContexts $contexts, ContextExams $exams, QuestionLibrary $library): Response
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
            'settings_form' => $host->canManage ? [
                'defaults' => SessionExamSettings::DEFAULTS,
                'labels' => SessionExamSettings::labels(),
                'retake_policies' => collect(RetakePolicy::cases())->mapWithKeys(fn (RetakePolicy $policy): array => [$policy->value => $policy->getLabel()]),
                'result_visibility' => collect(ResultVisibility::cases())->mapWithKeys(fn (ResultVisibility $visibility): array => [$visibility->value => $visibility->getLabel()]),
            ] : null,
            'library_url' => $host->canManage && $library->canAccess($request->user(), Tenant::current()) ? route('exams.library') : null,
            'banks' => $host->canManage ? $library->banks($request->user())
                ->withCount(['questions' => fn (Builder $query) => $query->active()
                    ->where(fn (Builder $visible) => $library->visibleTo($visible, $request->user()))])
                ->orderBy('title')->get()->map(fn (QuestionBank $bank): array => [
                    'id' => $bank->id, 'title' => $bank->title, 'question_count' => $bank->questions_count,
                    'is_mine' => $bank->owner_id === $request->user()->id,
                ]) : [],
            'exams' => $records->map(fn (Exam $exam): array => [
                'id' => $exam->id, 'title' => $exam->title, 'is_published' => $exam->is_published,
                'starts_at' => $exam->starts_at?->toIso8601String(), 'ends_at' => $exam->ends_at?->toIso8601String(),
                'attempts_count' => $host->canManage ? $exam->attempts_count : null,
                'settings' => $host->canManage && $exams->canEditDraft($exam, $request->user())
                    ? array_intersect_key($exam->attributesToArray(), SessionExamSettings::DEFAULTS)
                    : null,
                'preview_url' => $host->canManage && $exam->question_snapshot !== null
                    ? route('exams.context.preview', [$type, $context, $exam])
                    : null,
                'delete_url' => $exam->attempts_count === 0 && $exams->canDelete($host, $exam, $request->user())
                    ? route('exams.context.destroy', [$type, $context, $exam])
                    : null,
                'take_url' => $host->canParticipate && $exam->is_published ? route('exams.show', $exam) : null,
            ]),
            'results' => $results,
        ]);
    }

    public function store(Request $request, string $type, int $context, ExamContexts $contexts, ContextExams $exams, QuestionLibrary $library): RedirectResponse
    {
        $host = $this->context($request, $type, $context, $contexts, manage: true);
        $data = $request->validate(SessionExamSettings::rules() + [
            'banks' => ['required', 'array', 'list', 'max:10'],
            'banks.*' => ['required', 'array:bank_id,question_count,question_ids'],
            'banks.*.bank_id' => ['required', 'integer', 'distinct'],
            'banks.*.question_count' => ['required', 'integer', 'between:1,50'],
            'banks.*.question_ids' => ['sometimes', 'required', 'array', 'list', 'max:50'],
            'banks.*.question_ids.*' => ['required', 'integer', 'distinct'],
        ], attributes: SessionExamSettings::labels() + [
            'banks' => 'بانک‌های سؤال', 'banks.*.bank_id' => 'بانک سؤال',
            'banks.*.question_count' => 'تعداد سؤال', 'banks.*.question_ids' => 'سؤال‌های انتخاب‌شده',
            'banks.*.question_ids.*' => 'سؤال انتخاب‌شده',
        ]);
        $exams->generate($host, $data, $request->user(), $library);

        return to_route('exams.context', [$type, $context])->with('status', 'پیش‌نویس آزمون جلسه ساخته شد.');
    }

    public function updateSettings(Request $request, string $type, int $context, Exam $exam, ExamContexts $contexts, ContextExams $exams): RedirectResponse
    {
        $host = $this->context($request, $type, $context, $contexts, manage: true);
        abort_unless($exams->query($host)->whereKey($exam->id)->exists(), 404);
        abort_unless($exam->assigned_by_id === $request->user()->id, 403);
        $settings = $request->validate(SessionExamSettings::rules(), attributes: SessionExamSettings::labels());
        $exams->updateSettings($host, $exam->id, $request->user(), $settings);

        return to_route('exams.context', [$type, $context])->with('status', 'تنظیمات آزمون ذخیره شد.');
    }

    public function bankQuestions(Request $request, string $type, int $context, int $bank, ExamContexts $contexts, QuestionLibrary $library): JsonResponse
    {
        $this->context($request, $type, $context, $contexts, manage: true);
        $actor = $request->user();
        $record = $library->banks($actor)->findOrFail($bank);
        $questions = $library->availableQuestions($record, $actor)->orderBy('exam_questions.id')
            ->get(['exam_questions.id', 'body', 'type', 'score'])
            ->map(fn (Question $question): array => [
                'id' => $question->id, 'body' => $question->body,
                'type' => $question->type->getLabel(), 'score' => $question->score,
            ]);

        return response()->json(['questions' => $questions], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function publish(Request $request, string $type, int $context, Exam $exam, ExamContexts $contexts, ContextExams $exams): RedirectResponse
    {
        $host = $this->context($request, $type, $context, $contexts, manage: true);
        abort_unless($exams->query($host)->whereKey($exam->id)->exists(), 404);
        $data = $request->validate(['is_published' => ['required', 'boolean']]);
        $exam->update(['is_published' => $data['is_published']]);

        return to_route('exams.context', [$type, $context])->with('status', 'وضعیت انتشار آزمون تغییر کرد.');
    }

    public function preview(Request $request, string $type, int $context, Exam $exam, ExamContexts $contexts, ContextExams $exams): JsonResponse
    {
        $host = $this->context($request, $type, $context, $contexts, manage: true);
        $exam = $exams->query($host)->whereNotNull('question_snapshot')->withCount('attempts')->findOrFail($exam->id);

        return response()->json([
            'questions' => collect($exam->question_snapshot)->map(fn (array $question, int $index): array => [
                'index' => $index, 'body' => $question['body'], 'score' => $question['score'],
                'type' => QuestionType::from($question['type'])->getLabel(),
                'options' => array_column($question['options'] ?? [], 'text'),
            ]),
            'revision' => $exams->questionRevision($exam),
            'can_reorder' => $exams->canEditDraft($exam, $request->user()),
            'shuffled' => $exam->shuffle_questions,
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function reorder(Request $request, string $type, int $context, Exam $exam, ExamContexts $contexts, ContextExams $exams): JsonResponse
    {
        $host = $this->context($request, $type, $context, $contexts, manage: true);
        $data = $request->validate([
            'revision' => ['required', 'string', 'size:64'],
            'order' => ['required', 'array', 'list', 'max:500'],
            'order.*' => ['required', 'integer', 'min:0', 'distinct'],
        ], attributes: ['revision' => 'نسخهٔ پیش‌نمایش', 'order' => 'ترتیب سؤال‌ها', 'order.*' => 'جایگاه سؤال']);
        $revision = $exams->reorder($host, $exam->id, $request->user(), $data['order'], $data['revision']);

        return response()->json(['message' => 'ترتیب سؤال‌ها ذخیره شد.', 'revision' => $revision]);
    }

    public function destroy(Request $request, string $type, int $context, Exam $exam, ExamContexts $contexts, ContextExams $exams): RedirectResponse
    {
        $host = $this->context($request, $type, $context, $contexts);
        $exams->delete($host, $exam->id, $request->user());

        return to_route('exams.context', [$type, $context])->with('status', 'آزمون از این جلسه حذف شد.');
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
                'correct' => $answer->correctAnswerText(),
                'requires_manual_grading' => $answer->requiresManualGrading(), 'max_score' => $answer->max_score, 'score' => $answer->score,
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
                if ($answer === null || ! $answer->requiresManualGrading() || ($score !== null && $score > $answer->max_score)) {
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

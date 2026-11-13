<?php

namespace Modules\Exam\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\Question;
use Modules\Exam\Support\ExamContext;
use Modules\Exam\Support\QuestionLibrary;
use Modules\Exam\Support\SessionExamSettings;

class ContextExams
{
    public function query(ExamContext $context): Builder
    {
        return Exam::query()->where('context_type', $context->type)->where('context_id', $context->id);
    }

    /**
     * @param  array{title: string, duration_minutes: int, total_score: float, passing_score: float, banks: list<array{bank_id: int, question_count: int, question_ids?: list<int>}>, ...}  $data
     */
    public function generate(ExamContext $context, array $data, User $actor, QuestionLibrary $library): Exam
    {
        abort_unless($context->canManage, 403);

        return DB::transaction(function () use ($context, $data, $actor, $library): Exam {
            $banks = $library->banks($actor)->whereKey(array_column($data['banks'], 'bank_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $selections = [];
            $usedIds = [];
            foreach ($data['banks'] as $index => $selection) {
                if (! $banks->has($selection['bank_id'])) {
                    throw ValidationException::withMessages(["banks.{$index}.bank_id" => 'این بانک سؤال در دسترس شما نیست.']);
                }
            }

            // Reserve explicit choices before any random draw, including for later rows.
            foreach ($data['banks'] as $index => $selection) {
                if (! isset($selection['question_ids'])) {
                    continue;
                }

                $ids = $selection['question_ids'];
                $questions = $library->availableQuestions($banks->get($selection['bank_id']), $actor)
                    ->whereKey($ids)->get()->keyBy('id');
                if (count($ids) !== (int) $selection['question_count'] || $questions->count() !== count($ids) || array_intersect($ids, $usedIds) !== []) {
                    throw ValidationException::withMessages(["banks.{$index}.question_ids" => 'سؤال‌های انتخاب‌شده را بررسی کنید. تعداد باید مطابق ردیف باشد و هر سؤال باید فعال، در دسترس و غیرتکراری باشد.']);
                }

                $selections[$index] = collect($ids)->map(fn (int $id): Question => $questions->get($id));
                $usedIds = [...$usedIds, ...$ids];
            }

            foreach ($data['banks'] as $index => $selection) {
                if (isset($selections[$index])) {
                    continue;
                }

                $questions = $library->availableQuestions($banks->get($selection['bank_id']), $actor)
                    ->whereNotIn('exam_questions.id', $usedIds)
                    ->inRandomOrder()->limit($selection['question_count'])->get();

                if ($questions->count() < $selection['question_count']) {
                    throw ValidationException::withMessages(["banks.{$index}.question_count" => 'این بانک به تعداد درخواستی سؤال فعال و غیرتکراری ندارد.']);
                }

                $selections[$index] = $questions;
                $usedIds = [...$usedIds, ...$questions->modelKeys()];
            }

            ksort($selections);
            $exam = new Exam(array_intersect_key($data, SessionExamSettings::DEFAULTS));
            $exam->forceFill([
                'context_type' => $context->type,
                'context_id' => $context->id,
                'tenant_id' => $context->tenantId,
                'assigned_by_id' => $actor->id,
                'starts_at' => $context->startsAt,
                'ends_at' => $context->endsAt,
                'question_snapshot' => collect($selections)->collapse()->map(fn (Question $question): array => $question->only([
                    'type', 'body', 'options', 'accepted_answers', 'model_answer', 'score',
                ]))->all(),
            ])->save();

            return $exam;
        });
    }

    public function canDelete(ExamContext $context, Exam $exam, User $actor): bool
    {
        return $context->canManage && ($exam->assigned_by_id === $actor->id
            || ($exam->assigned_by_id === null && Gate::forUser($actor)->allows('delete', $exam)));
    }

    public function canEditDraft(Exam $exam, User $actor): bool
    {
        return $exam->assigned_by_id === $actor->id && ! $exam->is_published && $exam->attempts_count === 0;
    }

    public function updateSettings(ExamContext $context, int $examId, User $actor, array $settings): void
    {
        abort_unless($context->canManage, 403);

        DB::transaction(function () use ($context, $examId, $actor, $settings): void {
            $exam = $this->query($context)->lockForUpdate()->findOrFail($examId);
            abort_unless($exam->assigned_by_id === $actor->id, 403);
            $exam->loadCount('attempts');
            if (! $this->canEditDraft($exam, $actor)) {
                throw ValidationException::withMessages(['settings' => 'تنظیمات فقط برای پیش‌نویس بدون شرکت‌کننده قابل ویرایش است.']);
            }

            $exam->update(array_intersect_key($settings, SessionExamSettings::DEFAULTS));
        });
    }

    public function questionRevision(Exam $exam): string
    {
        return hash('sha256', json_encode([$exam->question_snapshot, $exam->total_score, $exam->shuffle_questions], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<int>  $order
     * @param  list<float>|null  $scores  In the submitted order.
     * @return array{revision: string, shuffled: bool}
     */
    public function updateQuestions(ExamContext $context, int $examId, User $actor, array $order, string $revision, ?array $scores, bool $fixOrder): array
    {
        abort_unless($context->canManage, 403);

        return DB::transaction(function () use ($context, $examId, $actor, $order, $revision, $scores, $fixOrder): array {
            $exam = $this->query($context)->whereNotNull('question_snapshot')->lockForUpdate()->findOrFail($examId);
            abort_unless($exam->assigned_by_id === $actor->id, 403);

            $exam->loadCount('attempts');
            if (! $this->canEditDraft($exam, $actor)) {
                throw ValidationException::withMessages(['order' => 'ترتیب و بارم سؤال‌ها فقط برای پیش‌نویس بدون شرکت‌کننده قابل تغییر است.']);
            }

            if ($revision !== $this->questionRevision($exam)) {
                throw ValidationException::withMessages(['order' => 'پیش‌نمایش تغییر کرده است. آن را دوباره باز کنید.']);
            }

            $questions = $exam->question_snapshot;
            if (count($order) !== count($questions) || array_diff(array_keys($questions), $order) !== []) {
                throw ValidationException::withMessages(['order' => 'ترتیب باید شامل همهٔ سؤال‌های آزمون، هر کدام یک‌بار، باشد.']);
            }
            if ($scores !== null && count($scores) !== count($questions)) {
                throw ValidationException::withMessages(['scores' => 'برای هر سؤال باید یک بارم ثبت شود.']);
            }

            $snapshot = [];
            foreach ($order as $position => $index) {
                $question = $questions[$index];
                if ($scores !== null) {
                    $question['score'] = (float) $scores[$position];
                }
                $snapshot[] = $question;
            }

            $exam->forceFill([
                'question_snapshot' => $snapshot,
                'shuffle_questions' => ! $fixOrder && array_map('intval', $order) === array_keys($questions) && $exam->shuffle_questions,
            ])->save();

            return ['revision' => $this->questionRevision($exam->refresh()), 'shuffled' => $exam->shuffle_questions];
        });
    }

    public function delete(ExamContext $context, int $examId, User $actor): void
    {
        abort_unless($context->canManage, 403);

        DB::transaction(function () use ($context, $examId, $actor): void {
            // Serialize deletion with the first attempt, which locks the same exam.
            $exam = $this->query($context)->lockForUpdate()->findOrFail($examId);
            abort_unless($this->canDelete($context, $exam, $actor), 403);

            if ($exam->attempts()->exists()) {
                throw ValidationException::withMessages(['delete_exam' => 'حذف ممکن نیست؛ دست‌کم یک نفر این آزمون را شروع کرده است.']);
            }

            $exam->delete();
        });
    }
}

<?php

namespace Modules\Exam\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Models\Exam;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;
use Modules\Exam\Support\ExamContext;
use Modules\Exam\Support\QuestionLibrary;

class ContextExams
{
    public function query(ExamContext $context): Builder
    {
        return Exam::query()->where('context_type', $context->type)->where('context_id', $context->id);
    }

    /**
     * @param  array{title: string, duration_minutes: int, total_score: float, passing_score: float, banks: list<array{bank_id: int, question_count: int}>}  $data
     */
    public function generate(ExamContext $context, array $data, User $actor, QuestionLibrary $library): Exam
    {
        abort_unless($context->canManage, 403);

        return DB::transaction(function () use ($context, $data, $actor, $library): Exam {
            $banks = $library->banks($actor)->whereKey(array_column($data['banks'], 'bank_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $drawn = collect();
            foreach ($data['banks'] as $index => $selection) {
                /** @var QuestionBank|null $bank */
                $bank = $banks->get($selection['bank_id']);
                if ($bank === null) {
                    throw ValidationException::withMessages(["banks.{$index}.bank_id" => 'این بانک سؤال در دسترس شما نیست.']);
                }

                $questions = $bank->questions()->active()
                    ->where(fn (Builder $query) => $library->visibleTo($query, $actor))
                    ->whereNotIn('exam_questions.id', $drawn->keys())
                    ->inRandomOrder()->limit($selection['question_count'])->get();

                if ($questions->count() < $selection['question_count']) {
                    throw ValidationException::withMessages(["banks.{$index}.question_count" => 'این بانک به تعداد درخواستی سؤال فعال و غیرتکراری ندارد.']);
                }

                foreach ($questions as $question) {
                    $drawn->put($question->id, $question);
                }
            }

            $exam = new Exam;
            $exam->forceFill([
                'context_type' => $context->type,
                'context_id' => $context->id,
                'tenant_id' => $context->tenantId,
                'assigned_by_id' => $actor->id,
                'starts_at' => $context->startsAt,
                'ends_at' => $context->endsAt,
                'title' => $data['title'],
                'duration_minutes' => $data['duration_minutes'],
                'total_score' => $data['total_score'],
                'passing_score' => $data['passing_score'],
                'question_snapshot' => $drawn->values()->map(fn (Question $question): array => $question->only([
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

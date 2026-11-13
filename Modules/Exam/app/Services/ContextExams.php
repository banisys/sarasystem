<?php

namespace Modules\Exam\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Models\Exam;
use Modules\Exam\Support\ExamContext;

class ContextExams
{
    public function query(ExamContext $context): Builder
    {
        return Exam::query()->where('context_type', $context->type)->where('context_id', $context->id);
    }

    /** Copies settings and bank sections, never participants or past attempts. */
    public function assign(ExamContext $context, int $templateId, User $actor): Exam
    {
        abort_unless($context->canManage, 403);

        return DB::transaction(function () use ($context, $templateId, $actor): Exam {
            $template = Exam::query()->published()->whereNull('context_type')->lockForUpdate()->find($templateId);
            if ($template === null) {
                throw ValidationException::withMessages(['template_id' => 'یک آزمون منتشرشده از همین '.config('sarasystem.tenant_label').' انتخاب کنید.']);
            }

            if ($existing = $this->query($context)->where('template_exam_id', $templateId)->first()) {
                return $existing;
            }

            $exam = $template->replicate()->unsetRelations();
            $exam->forceFill([
                'context_type' => $context->type,
                'context_id' => $context->id,
                'template_exam_id' => $templateId,
                'assigned_by_id' => $actor->id,
                'tenant_id' => $context->tenantId,
                'title' => Str::limit($template->title.' — '.$context->title, 255, ''),
                'starts_at' => $context->startsAt,
                'ends_at' => $context->endsAt,
            ])->save();
            foreach ($template->sections()->get() as $section) {
                $exam->sections()->create($section->only(['question_bank_id', 'question_count', 'sort']));
            }

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

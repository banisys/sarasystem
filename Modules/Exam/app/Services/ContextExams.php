<?php

namespace Modules\Exam\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
    public function assign(ExamContext $context, int $templateId): Exam
    {
        abort_unless($context->canManage, 403);

        return DB::transaction(function () use ($context, $templateId): Exam {
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
}

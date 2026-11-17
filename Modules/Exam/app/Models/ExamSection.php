<?php

namespace Modules\Exam\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bank draw or an explicit question selection for part of an exam.
 * Belongs to its exam's tenant; only reached through the exam.
 */
#[Fillable(['exam_id', 'question_bank_id', 'question_count', 'question_ids', 'sort'])]
class ExamSection extends Model
{
    protected $table = 'exam_sections';

    public $timestamps = false;

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * @return BelongsTo<QuestionBank, $this>
     */
    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class);
    }

    protected function casts(): array
    {
        return [
            'question_count' => 'integer',
            'question_ids' => 'array',
            'sort' => 'integer',
        ];
    }
}

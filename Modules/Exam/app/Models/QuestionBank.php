<?php

namespace Modules\Exam\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Exam\Database\Factories\QuestionBankFactory;

/**
 * A named set of questions of one tenant. A question may be in several
 * banks; exams draw their questions from banks (ADR 0013).
 */
#[Fillable(['tenant_id', 'title', 'description'])]
class QuestionBank extends Model
{
    /** @use HasFactory<QuestionBankFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'exam_question_banks';

    protected static function newFactory(): QuestionBankFactory
    {
        return QuestionBankFactory::new();
    }

    /**
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_bank_question');
    }
}

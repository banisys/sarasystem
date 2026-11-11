<?php

namespace Modules\Exam\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Exam\Database\Factories\QuestionTagFactory;

#[Fillable(['tenant_id', 'title'])]
class QuestionTag extends Model
{
    /** @use HasFactory<QuestionTagFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'exam_question_tags';

    protected static function newFactory(): QuestionTagFactory
    {
        return QuestionTagFactory::new();
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_question_question_tag');
    }
}

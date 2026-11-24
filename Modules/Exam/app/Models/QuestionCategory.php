<?php

namespace Modules\Exam\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasCategoryParent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Exam\Database\Factories\QuestionCategoryFactory;

#[Fillable(['tenant_id', 'parent_id', 'title'])]
class QuestionCategory extends Model
{
    /** @use HasFactory<QuestionCategoryFactory> */
    use BelongsToTenant, HasCategoryParent, HasFactory;

    protected $table = 'exam_question_categories';

    protected static function newFactory(): QuestionCategoryFactory
    {
        return QuestionCategoryFactory::new();
    }

    /** @return BelongsTo<QuestionCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<QuestionCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'category_id');
    }
}

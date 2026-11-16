<?php

namespace Modules\Exam\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Exam\Database\Factories\ExamGroupFactory;

#[Fillable(['tenant_id', 'title'])]
class ExamGroup extends Model
{
    /** @use HasFactory<ExamGroupFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ExamGroupFactory
    {
        return ExamGroupFactory::new();
    }

    /** @return HasMany<Exam, $this> */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}

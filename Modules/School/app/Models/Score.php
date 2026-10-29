<?php

namespace Modules\School\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Class\Models\Subject;
use Modules\School\Enums\DescriptiveLevel;

/**
 * A learner's score in a subject for a score period (ADR 0032): a number
 * out of 20 or a descriptive level, by the stage's scale, with a note.
 */
#[Fillable(['tenant_id', 'period_id', 'user_id', 'subject_id', 'classroom_id', 'score', 'level', 'note', 'entered_by_id', 'entered_at'])]
class Score extends Model
{
    use BelongsToTenant;

    protected $table = 'school_scores';

    /**
     * @return BelongsTo<ScorePeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(ScorePeriod::class, 'period_id')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withoutGlobalScopes();
    }

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'level' => DescriptiveLevel::class,
            'entered_at' => 'datetime',
        ];
    }
}

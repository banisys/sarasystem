<?php

namespace Modules\School\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Class\Models\Subject;

/**
 * A subject of a grade's curriculum (ADR 0031), for one major or for all,
 * with its coefficient (ضریب) in averages and its weekly hours. Belongs to
 * its grade's tenant.
 */
#[Fillable(['grade_id', 'major_id', 'subject_id', 'coefficient', 'weekly_hours', 'sort'])]
class Curriculum extends Model
{
    protected $table = 'school_curricula';

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Major, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class)->withoutGlobalScopes();
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
            'coefficient' => 'decimal:2',
            'weekly_hours' => 'integer',
        ];
    }
}

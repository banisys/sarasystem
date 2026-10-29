<?php

namespace Modules\Class\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One topic of a teaching plan («سرفصل»), e.g. «فصل ۲: عددهای صحیح»,
 * taking some sessions (ADR 0025). Belongs to its plan's tenant.
 */
#[Fillable(['teaching_plan_id', 'sort', 'title', 'description', 'sessions'])]
class PlanTopic extends Model
{
    protected $table = 'class_plan_topics';

    /**
     * @return BelongsTo<TeachingPlan, $this>
     */
    public function teachingPlan(): BelongsTo
    {
        return $this->belongsTo(TeachingPlan::class)->withoutGlobalScopes();
    }

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'sessions' => 'integer',
        ];
    }
}

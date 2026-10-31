<?php

namespace Modules\Tutorial\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row made while a tutorial run's mode was on (ADR 0040). `model_type`
 * is the model's class, so the row can be found and deleted again.
 */
#[Fillable(['run_id', 'model_type', 'model_id'])]
class TutorialRecord extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<TutorialRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(TutorialRun::class, 'run_id');
    }
}

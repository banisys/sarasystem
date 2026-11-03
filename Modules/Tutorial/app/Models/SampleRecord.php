<?php

namespace Modules\Tutorial\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row made by a sample-data batch (ADR 0046). `model_type` is the
 * model's class, so the row can be found and deleted again.
 */
#[Fillable(['batch_id', 'model_type', 'model_id'])]
class SampleRecord extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tutorial_sample_records';

    /**
     * @return BelongsTo<SampleBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(SampleBatch::class, 'batch_id');
    }
}

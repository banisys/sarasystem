<?php

namespace Modules\Tutorial\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Tutorial\Models\SampleBatch;
use Modules\Tutorial\Models\SampleRecord;
use Modules\Tutorial\Models\TutorialRecord;
use Modules\Tutorial\Models\TutorialRun;
use Spatie\Permission\Models\Permission;

/**
 * Notes every row made while a sample-data batch is being built against
 * the batch (ADR 0046), so that deleting the batch deletes them. Only
 * rows made inside during() in this process are noted.
 */
class SampleRecorder
{
    /**
     * Never recorded: the tutorial's own rows, and permissions, which are
     * part of the installation.
     *
     * @var list<class-string>
     */
    public const array IGNORED = [SampleBatch::class, SampleRecord::class, TutorialRun::class, TutorialRecord::class, Permission::class];

    private ?SampleBatch $batch = null;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function during(SampleBatch $batch, Closure $callback): mixed
    {
        $previous = $this->batch;
        $this->batch = $batch;

        try {
            return $callback();
        } finally {
            $this->batch = $previous;
        }
    }

    public function created(Model $model): void
    {
        if ($this->batch === null || $model instanceof Pivot || $model->getKey() === null || in_array($model::class, self::IGNORED, true)) {
            return;
        }

        SampleRecord::query()->create([
            'batch_id' => $this->batch->getKey(),
            'model_type' => $model::class,
            'model_id' => (string) $model->getKey(),
        ]);
    }
}

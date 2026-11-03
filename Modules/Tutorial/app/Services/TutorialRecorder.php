<?php

namespace Modules\Tutorial\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Tutorial\Models\TutorialRecord;

/**
 * Notes every row made in tutorial mode against the run (ADR 0040), so
 * that starting again can delete it. Listens to every model's `created`
 * event; rows made by queue workers or commands (no tutorial session) are
 * not seen. Its own rows and permissions are not recorded
 * (SampleRecorder::IGNORED).
 */
class TutorialRecorder
{
    public function __construct(private TutorialMode $mode) {}

    public function created(Model $model): void
    {
        if ($model instanceof Pivot || $model->getKey() === null || in_array($model::class, SampleRecorder::IGNORED, true)) {
            return;
        }

        if (! $this->mode->isRecording()) {
            return;
        }

        TutorialRecord::query()->create([
            'run_id' => $this->mode->run()->getKey(),
            'model_type' => $model::class,
            'model_id' => (string) $model->getKey(),
        ]);
    }
}

<?php

namespace Modules\Tutorial\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Tutorial\Models\TutorialRecord;
use Modules\Tutorial\Models\TutorialRun;
use Spatie\Permission\Models\Permission;

/**
 * Notes every row made in tutorial mode against the run (ADR 0040), so
 * that starting again can delete it. Listens to every model's `created`
 * event; rows made by queue workers or commands (no tutorial session) are
 * not seen.
 */
class TutorialRecorder
{
    /**
     * Never recorded: the tutorial's own rows, and permissions, which are
     * part of the installation.
     *
     * @var list<class-string>
     */
    private const array IGNORED = [TutorialRun::class, TutorialRecord::class, Permission::class];

    public function __construct(private TutorialMode $mode) {}

    public function created(Model $model): void
    {
        if ($model instanceof Pivot || $model->getKey() === null || in_array($model::class, self::IGNORED, true)) {
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

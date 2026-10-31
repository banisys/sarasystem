<?php

namespace Modules\Tutorial\Services;

use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Mission;
use Modules\Tutorial\Scenarios\Scenario;
use Modules\Tutorial\Scenarios\Scenarios;

/**
 * Missions are done in order (ADR 0040): only the current one is checked,
 * and once done it stays ticked and the next one opens.
 */
class TutorialProgress
{
    public function __construct(private Scenarios $scenarios) {}

    public function scenario(TutorialRun $run): Scenario
    {
        return $this->scenarios->find($run->scenario) ?? abort(404);
    }

    /**
     * Ticks the missions that are now done and returns the current one, or
     * null when the scenario is finished.
     */
    public function refresh(TutorialRun $run): ?Mission
    {
        $completed = $run->completed_missions ?? [];
        $current = null;

        foreach ($this->scenario($run)->missions() as $mission) {
            if (array_key_exists($mission->key, $completed)) {
                continue;
            }

            if (! $mission->isDone($run)) {
                $current = $mission;
                break;
            }

            $completed[$mission->key] = now()->toIso8601String();
        }

        if ($completed !== ($run->completed_missions ?? [])) {
            $run->update(['completed_missions' => $completed, 'completed_at' => $current === null ? now() : null]);
        }

        return $current;
    }

    public function completedCount(TutorialRun $run): int
    {
        return count($run->completed_missions ?? []);
    }

    public function total(TutorialRun $run): int
    {
        return count($this->scenario($run)->missions());
    }
}

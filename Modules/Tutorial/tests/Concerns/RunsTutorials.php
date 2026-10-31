<?php

namespace Modules\Tutorial\Tests\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Services\TutorialMode;

trait RunsTutorials
{
    protected function startTutorial(User $owner, string $scenario = 'language-institute'): TutorialRun
    {
        $run = TutorialRun::query()->create(['user_id' => $owner->id, 'scenario' => $scenario, 'completed_missions' => []]);
        $this->withSession([TutorialMode::SESSION_KEY => $run->id]);

        return $run;
    }

    /**
     * Notes rows as made in the run, as tutorial mode would have.
     */
    protected function madeInRun(TutorialRun $run, Model ...$models): void
    {
        foreach ($models as $model) {
            $run->records()->create(['model_type' => $model::class, 'model_id' => (string) $model->getKey()]);
        }
    }
}

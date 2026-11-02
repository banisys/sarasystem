<?php

namespace Modules\Tutorial\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Mission;
use RuntimeException;
use Throwable;

/**
 * «انجام خودکار همه‌ی گام‌ها» (ADR 0040): does the run's remaining missions
 * in order, in tutorial mode, so their rows are recorded like the person's
 * own and «شروع دوباره» deletes them. Each mission is all or nothing; on
 * the first one that fails it stops, and the missions before it stay done.
 */
class TutorialAutoComplete
{
    public function __construct(private TutorialMode $mode, private TutorialProgress $progress) {}

    /**
     * @return int the missions done
     *
     * @throws RuntimeException with a message for the person, naming the mission
     */
    public function complete(TutorialRun $run, User $owner): int
    {
        $this->mode->enter($run);
        $done = 0;

        while (($mission = $this->progress->refresh($run)) !== null) {
            if (! $mission->canPerform()) {
                throw $this->failure($mission, 'این گام را باید خودتان انجام دهید.');
            }

            try {
                DB::transaction(fn () => $mission->performFor($run, $owner));
            } catch (Throwable $exception) {
                report($exception);

                throw $this->failure($mission, $exception instanceof QueryException ? 'خطای پایگاه داده.' : $exception->getMessage(), $exception);
            }

            if (! $mission->isDone($run->refresh())) {
                throw $this->failure($mission, 'کار انجام شد ولی شرط این گام برقرار نشد.');
            }

            $done++;
        }

        return $done;
    }

    private function failure(Mission $mission, string $reason, ?Throwable $previous = null): RuntimeException
    {
        return new RuntimeException("گام «{$mission->title}» انجام نشد: {$reason}", previous: $previous);
    }
}

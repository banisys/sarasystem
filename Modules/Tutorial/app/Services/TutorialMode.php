<?php

namespace Modules\Tutorial\Services;

use App\Auth\Impersonation;
use App\Models\User;
use Closure;
use Modules\Tutorial\Models\TutorialRun;

/**
 * Whether this browser session is in tutorial mode, and for which run
 * (ADR 0040). The mode is kept in the session, so it also holds while the
 * person signs in as someone else for a mission or fills a public form;
 * it only counts while the run's owner is signed in (or impersonating).
 */
class TutorialMode
{
    public const string SESSION_KEY = 'tutorial.run_id';

    private ?TutorialRun $run = null;

    /** The session value and owner the run was looked up for. */
    private ?string $resolvedFor = null;

    private bool $paused = false;

    /**
     * The person doing the tutorial: the signed-in user, or the one behind
     * an impersonation.
     */
    public static function owner(): ?User
    {
        return Impersonation::impersonator() ?? auth()->user();
    }

    public function run(): ?TutorialRun
    {
        if (! app()->bound('session.store') || ! session()->has(self::SESSION_KEY)) {
            return null;
        }

        $owner = self::owner();

        if ($owner === null) {
            return null;
        }

        $runId = session(self::SESSION_KEY);
        $for = $runId.':'.$owner->getKey();

        if ($this->resolvedFor !== $for) {
            $this->resolvedFor = $for;
            $this->run = TutorialRun::query()->whereKey($runId)->where('user_id', $owner->getKey())->first();
        }

        return $this->run;
    }

    public function enter(TutorialRun $run): void
    {
        session()->put(self::SESSION_KEY, $run->getKey());
        $this->resolvedFor = null;
    }

    public function leave(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->resolvedFor = null;
        $this->run = null;
    }

    /**
     * Whether rows made now belong to a run.
     */
    public function isRecording(): bool
    {
        return ! $this->paused && $this->run() !== null;
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function withoutRecording(Closure $callback): mixed
    {
        $paused = $this->paused;
        $this->paused = true;

        try {
            return $callback();
        } finally {
            $this->paused = $paused;
        }
    }
}

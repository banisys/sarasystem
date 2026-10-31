<?php

namespace Modules\Tutorial\Http\Controllers;

use App\Auth\Impersonation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Modules\Tutorial\Services\TutorialGuide;
use Modules\Tutorial\Services\TutorialMode;
use Modules\Tutorial\Services\TutorialProgress;

/**
 * «ورود با حساب …»: signs the tutorial's owner in as the account the
 * current mission is done with, made earlier in the run (ADR 0040).
 */
class ActAsController extends Controller
{
    public function __invoke(TutorialMode $mode, TutorialProgress $progress): RedirectResponse
    {
        $run = $mode->run() ?? abort(404);
        $mission = $progress->refresh($run) ?? abort(404);
        $target = $mission->actingAs($run) ?? abort(404);

        if (auth()->id() !== $target->getKey()) {
            if (Impersonation::isActive()) {
                Impersonation::stop();
            }

            Impersonation::start(User::query()->findOrFail($run->user_id), $target, TutorialGuide::pageUrl());
        }

        return redirect($mission->urlFor($run) ?? '/');
    }
}

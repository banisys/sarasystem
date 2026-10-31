<?php

namespace Modules\Tutorial\Services;

use App\Auth\Impersonation;
use App\Models\Tenant;
use Modules\Tutorial\Filament\Pages\StepByStepTutorial;
use Modules\Tutorial\Models\TutorialRun;
use Modules\Tutorial\Scenarios\Mission;

/**
 * What the tutorial bar shows on every page while in tutorial mode
 * (ADR 0040): the current mission, where to do it and as whom. Used by the
 * admin panel's bar and by the Inertia pages (shared prop `tutorial`).
 */
class TutorialGuide
{
    public function __construct(private TutorialMode $mode, private TutorialProgress $progress) {}

    /**
     * @return array{scenario: string, done: int, total: int, finished: bool, mission: array{key: string, title: string, actor: string, steps: list<string>, url: string|null, urlLabel: string}|null, actAs: array{name: string, url: string}|null, ownAccountUrl: string|null, pageUrl: string}|null
     */
    public function data(): ?array
    {
        $run = $this->mode->run();

        if ($run === null) {
            return null;
        }

        $mission = $this->progress->refresh($run);

        return [
            'scenario' => $this->progress->scenario($run)->title(),
            'done' => $this->progress->completedCount($run),
            'total' => $this->progress->total($run),
            'finished' => $mission === null,
            'mission' => $mission ? [
                'key' => $mission->key,
                'title' => $mission->title,
                'actor' => $mission->actorLabel(),
                'steps' => $mission->stepList(),
                'url' => $mission->urlFor($run),
                'urlLabel' => $mission->urlLabel(),
            ] : null,
            'actAs' => $mission ? $this->actAs($run, $mission) : null,
            // A mission done with one's own account while signed in as someone else.
            'ownAccountUrl' => ($mission === null || ! $mission->needsAnotherAccount()) && Impersonation::isActive() ? route('impersonation.leave') : null,
            'pageUrl' => self::pageUrl(),
        ];
    }

    public static function pageUrl(): string
    {
        return StepByStepTutorial::getUrl(panel: 'admin', tenant: Tenant::primary() ?? Tenant::query()->active()->first());
    }

    /**
     * @return array{name: string, url: string}|null
     */
    private function actAs(TutorialRun $run, Mission $mission): ?array
    {
        $user = $mission->actingAs($run);

        return $user !== null && auth()->id() !== $user->getKey()
            ? ['name' => $user->name, 'url' => route('tutorial.act-as')]
            : null;
    }
}

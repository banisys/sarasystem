<?php

namespace Modules\Tutorial\Scenarios;

use App\Models\User;
use Closure;
use Modules\Tutorial\Models\TutorialRun;

/**
 * One task of a tutorial scenario (ADR 0040): what to do, who does it,
 * where, and how to tell it is done. The check looks at the rows made in
 * the run, so it doesn't matter how the person got there.
 */
final class Mission
{
    /** @var list<string> */
    private array $steps = [];

    private string $actor = 'خودتان (مدیر کل سیستم)';

    private ?Closure $actingAs = null;

    private ?Closure $url = null;

    private ?string $urlLabel = null;

    private ?Closure $doneWhen = null;

    private function __construct(public readonly string $key, public readonly string $title) {}

    public static function make(string $key, string $title): self
    {
        return new self($key, $title);
    }

    /**
     * @param  list<string>  $steps
     */
    public function steps(array $steps): self
    {
        $this->steps = $steps;

        return $this;
    }

    /**
     * Who does it, and the account made earlier in the run to sign in with.
     *
     * @param  (Closure(TutorialRun): ?User)|null  $actingAs
     */
    public function actor(string $actor, ?Closure $actingAs = null): self
    {
        $this->actor = $actor;
        $this->actingAs = $actingAs;

        return $this;
    }

    /**
     * @param  Closure(TutorialRun): ?string  $url
     */
    public function url(Closure $url, string $label = 'برو به صفحه'): self
    {
        $this->url = $url;
        $this->urlLabel = $label;

        return $this;
    }

    /**
     * @param  Closure(TutorialRun): bool  $check
     */
    public function doneWhen(Closure $check): self
    {
        $this->doneWhen = $check;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function stepList(): array
    {
        return $this->steps;
    }

    public function actorLabel(): string
    {
        return $this->actor;
    }

    public function actingAs(TutorialRun $run): ?User
    {
        return $this->actingAs ? ($this->actingAs)($run) : null;
    }

    public function needsAnotherAccount(): bool
    {
        return $this->actingAs !== null;
    }

    public function urlFor(TutorialRun $run): ?string
    {
        return $this->url ? ($this->url)($run) : null;
    }

    public function urlLabel(): string
    {
        return $this->urlLabel ?? 'برو به صفحه';
    }

    public function isDone(TutorialRun $run): bool
    {
        return $this->doneWhen !== null && ($this->doneWhen)($run);
    }
}

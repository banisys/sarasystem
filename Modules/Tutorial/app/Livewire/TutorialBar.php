<?php

namespace Modules\Tutorial\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Tutorial\Services\TutorialGuide;

/**
 * The tutorial bar at the top of every admin-panel page in tutorial mode
 * (ADR 0040). It checks the current mission every few seconds, so a
 * mission done in a modal ticks without leaving the page.
 */
class TutorialBar extends Component
{
    /** Missions done when the bar last looked, to say «انجام شد». */
    public ?int $doneSeen = null;

    public ?string $justDone = null;

    public function render(): View
    {
        $guide = app(TutorialGuide::class)->data();
        $this->justDone = null;

        if ($guide !== null) {
            if ($this->doneSeen !== null && $guide['done'] > $this->doneSeen) {
                $this->justDone = 'گام انجام شد. گام بعدی را ببینید.';
            }

            $this->doneSeen = $guide['done'];
        }

        return view('tutorial::livewire.tutorial-bar', ['guide' => $guide]);
    }
}

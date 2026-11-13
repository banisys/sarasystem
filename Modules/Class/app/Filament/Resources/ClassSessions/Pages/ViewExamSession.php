<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use App\Models\User;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Services\SessionScene;

class ViewExamSession extends ViewClassSession
{
    protected function authorizeAccess(): void
    {
        abort_unless($this->session()->kind === SessionKind::Exam, 404);

        parent::authorizeAccess();
    }

    public function getHeading(): string
    {
        $subject = $this->session()->subject?->title;

        return $subject === null ? $this->pageLabel() : $this->pageLabel().' — '.$subject;
    }

    public function getHeader(): View
    {
        $session = $this->session();
        $scene = app(SessionScene::class);
        $user = auth()->user();

        return view('class::filament.exam-session-header', [
            'scene' => $user instanceof User && $scene->canSee($user, $session) ? $scene->for($session) : null,
        ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components(SessionActions::detailsEntries(exam: true))->columns(1);
    }

    protected function pageLabel(): string
    {
        return 'جلسهٔ امتحانی';
    }

    protected function isExamPage(): bool
    {
        return true;
    }
}

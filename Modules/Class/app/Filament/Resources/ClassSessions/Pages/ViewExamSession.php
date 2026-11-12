<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use Filament\Schemas\Schema;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Filament\Actions\SessionActions;

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

<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\ClassSession;
use Modules\Class\Services\SessionExamSource;
use Nwidart\Modules\Facades\Module;

/**
 * Everything about one session, with its actions in the header (ADR 0050).
 * A session on a calendar and «جزئیات» in the lists open it in a new tab.
 */
class ViewClassSession extends ViewRecord
{
    protected static string $resource = ClassSessionResource::class;

    /**
     * Not the resource's list permission: a class's «جلسه‌ها» tab links here
     * for those who may only see the class. ClassSessionResource::canView()
     * decides, on opening the page and on every request after.
     */
    public static function authorizeResourceAccess(): void {}

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->isExamPage() && $this->session()->kind === SessionKind::Exam) {
            $this->redirect(ClassSessionResource::detailsUrl($this->session()));
        }
    }

    public function hydrate(): void
    {
        $this->authorizeAccess();
    }

    public function getTitle(): string
    {
        return $this->getHeading().' · '.SessionActions::when($this->session());
    }

    /**
     * The subject alone: the time is in the page's first section, and stays
     * in the browser tab's title to tell open sessions apart.
     */
    public function getHeading(): string
    {
        return $this->session()->subject?->title ?? 'جلسه';
    }

    public function getSubheading(): ?string
    {
        return $this->session()->classroom?->title;
    }

    /**
     * @return array<string, string>|list<string>
     */
    public function getBreadcrumbs(): array
    {
        $classroom = $this->session()->classroom;

        return $classroom !== null && ClassroomResource::canView($classroom)
            ? [ClassroomResource::getUrl('sessions', ['record' => $classroom]) => $classroom->title, $this->pageLabel()]
            : [$this->pageLabel()];
    }

    protected function getHeaderActions(): array
    {
        $actions = SessionActions::pageActions(exam: $this->isExamPage());
        if (Module::isEnabled('Exam')) {
            $url = app(SessionExamSource::class)->link($this->session(), auth()->user());
            if ($url !== null) {
                $actions[] = Action::make('sessionExams')->label('آزمون‌های جلسه')->url($url)->openUrlInNewTab();
            }
        }

        return $actions;
    }

    protected function pageLabel(): string
    {
        return 'جلسه';
    }

    protected function isExamPage(): bool
    {
        return false;
    }

    protected function session(): ClassSession
    {
        /** @var ClassSession */
        return $this->getRecord();
    }
}

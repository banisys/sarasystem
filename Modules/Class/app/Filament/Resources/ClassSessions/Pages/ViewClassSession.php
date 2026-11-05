<?php

namespace Modules\Class\Filament\Resources\ClassSessions\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\ClassSession;

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

    public function hydrate(): void
    {
        abort_unless(ClassSessionResource::canView($this->getRecord()), 403);
    }

    public function getTitle(): string
    {
        return ($this->session()->subject?->title ?? 'جلسه').' · '.SessionActions::when($this->session());
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
            ? [ClassroomResource::getUrl('sessions', ['record' => $classroom]) => $classroom->title, 'جلسه']
            : ['جلسه'];
    }

    protected function getHeaderActions(): array
    {
        return SessionActions::pageActions();
    }

    private function session(): ClassSession
    {
        /** @var ClassSession */
        return $this->getRecord();
    }
}

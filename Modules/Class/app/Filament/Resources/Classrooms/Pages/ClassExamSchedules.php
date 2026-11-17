<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use Modules\Class\Filament\Resources\Classrooms\ClassroomResource;

/** Redirect old exam schedule links to the calendar. */
class ClassExamSchedules extends ClassSessions
{
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->redirect(ClassroomResource::getUrl('sessions', ['record' => $this->getRecord()]));
    }
}

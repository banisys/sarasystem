<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SchedulesRelationManager;

class ClassSchedules extends ClassroomSectionPage
{
    protected static function manager(): string
    {
        return SchedulesRelationManager::class;
    }

    protected static function sectionTitle(): string
    {
        return 'برنامه';
    }
}

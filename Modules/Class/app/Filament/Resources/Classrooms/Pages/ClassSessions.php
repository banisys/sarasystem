<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use Modules\Class\Filament\Resources\Classrooms\RelationManagers\SessionsRelationManager;

class ClassSessions extends ClassroomSectionPage
{
    protected static function manager(): string
    {
        return SessionsRelationManager::class;
    }

    protected static function sectionTitle(): string
    {
        return 'جلسه‌ها';
    }
}

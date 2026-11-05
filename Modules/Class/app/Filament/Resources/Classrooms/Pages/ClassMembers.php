<?php

namespace Modules\Class\Filament\Resources\Classrooms\Pages;

use Modules\Class\Filament\Resources\Classrooms\RelationManagers\MembersRelationManager;

class ClassMembers extends ClassroomSectionPage
{
    protected static function manager(): string
    {
        return MembersRelationManager::class;
    }

    protected static function sectionTitle(): string
    {
        return 'اعضا';
    }
}

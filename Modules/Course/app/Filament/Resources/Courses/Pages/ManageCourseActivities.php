<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Modules\Course\Filament\Resources\Courses\RelationManagers\LessonsRelationManager;

class ManageCourseActivities extends ManageCourseContent
{
    protected static ?string $breadcrumb = 'فعالیت‌ها';

    protected static string $relationManager = LessonsRelationManager::class;
}

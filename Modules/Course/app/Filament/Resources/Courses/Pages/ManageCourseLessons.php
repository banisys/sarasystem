<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Modules\Course\Filament\Resources\Courses\RelationManagers\LessonsRelationManager;

class ManageCourseLessons extends ManageCourseContent
{
    protected static ?string $breadcrumb = 'درس‌ها';

    protected static string $relationManager = LessonsRelationManager::class;
}

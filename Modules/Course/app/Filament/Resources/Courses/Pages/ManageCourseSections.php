<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Modules\Course\Filament\Resources\Courses\RelationManagers\SectionsRelationManager;

class ManageCourseSections extends ManageCourseContent
{
    protected static ?string $breadcrumb = 'سرفصل‌ها';

    protected static string $relationManager = SectionsRelationManager::class;
}

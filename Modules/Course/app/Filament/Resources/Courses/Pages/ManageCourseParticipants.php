<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Modules\Course\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;

class ManageCourseParticipants extends ManageCourseContent
{
    protected static ?string $breadcrumb = 'شرکت‌کنندگان';

    protected static string $relationManager = EnrollmentsRelationManager::class;
}

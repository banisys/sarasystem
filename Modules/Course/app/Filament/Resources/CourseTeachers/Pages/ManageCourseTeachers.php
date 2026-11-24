<?php

namespace Modules\Course\Filament\Resources\CourseTeachers\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Course\Filament\Resources\CourseTeachers\CourseTeacherResource;

class ManageCourseTeachers extends ManageRecords
{
    protected static string $resource = CourseTeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('افزودن مدرس'),
        ];
    }
}

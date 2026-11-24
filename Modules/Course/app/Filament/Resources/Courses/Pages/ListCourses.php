<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Course\Filament\Resources\Courses\CourseResource;

class ListCourses extends ListRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

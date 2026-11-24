<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Course\Filament\Resources\Courses\CourseResource;

class CreateCourse extends CreateRecord
{
    protected static string $resource = CourseResource::class;
}

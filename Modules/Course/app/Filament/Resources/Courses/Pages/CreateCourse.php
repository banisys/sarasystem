<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Schemas\CourseForm;
use Modules\Course\Models\Course;

class CreateCourse extends CreateRecord
{
    protected static string $resource = CourseResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function handleRecordCreation(array $data): Course
    {
        return CourseForm::save(new Course, $data);
    }
}

<?php

namespace Modules\Course\Filament\Resources\CourseCategories\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Course\Filament\Resources\CourseCategories\CourseCategoryResource;

class ManageCourseCategories extends ManageRecords
{
    protected static string $resource = CourseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

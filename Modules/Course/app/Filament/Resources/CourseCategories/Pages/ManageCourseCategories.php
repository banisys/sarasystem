<?php

namespace Modules\Course\Filament\Resources\CourseCategories\Pages;

use App\Filament\Resources\Pages\ListCategoryTree;
use Illuminate\Database\Eloquent\Model;
use Modules\Course\Filament\Resources\CourseCategories\CourseCategoryResource;
use Modules\Course\Support\CourseCategoryTree;

class ManageCourseCategories extends ListCategoryTree
{
    protected static string $resource = CourseCategoryResource::class;

    protected function categoryTree(): CourseCategoryTree
    {
        return CourseCategoryTree::current(withCourseCounts: true);
    }

    protected function nodeCaption(Model $record): string
    {
        return ($record->courses_count + $record->additional_courses_count).' دوره';
    }

    protected function nodeDetails(Model $record): ?string
    {
        return $record->is_active ? null : 'غیرفعال';
    }

    protected function linkedItemLabel(): string
    {
        return 'دوره';
    }
}

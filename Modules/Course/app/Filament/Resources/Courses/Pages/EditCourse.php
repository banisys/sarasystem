<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Schemas\CourseForm;
use Modules\Course\Models\Course;

class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => ! $this->getRecord()->lessons()->exists()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $course = $this->getRecord();

        $data['category_ids'] = array_values(array_filter([
            $course->course_category_id,
            ...$course->additionalCategories()->orderBy('course_categories.id')->pluck('course_categories.id')->all(),
        ]));
        $data['has_prepayment'] = $course->prepayment_amount !== null;
        $data['description_html'] ??= filled($course->description) ? '<p>'.nl2br(e($course->description), false).'</p>' : null;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Course
    {
        return CourseForm::save($record, $data);
    }
}

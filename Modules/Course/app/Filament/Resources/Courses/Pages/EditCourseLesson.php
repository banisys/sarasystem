<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Schemas\CourseLessonForm;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseLesson;

/** @extends EditRecord<CourseLesson> */
class EditCourseLesson extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected static ?string $title = 'ویرایش درس';

    protected static ?string $breadcrumb = 'ویرایش درس';

    protected ?bool $hasDatabaseTransactions = true;

    #[Locked]
    public Course $ownerCourse;

    public function mount(int|string $record, int|string|null $course = null): void
    {
        abort_if($course === null, 404);

        $this->ownerCourse = parent::resolveRecord($course);

        parent::mount($record);
    }

    protected function resolveRecord(int|string $key): CourseLesson
    {
        return $this->ownerCourse->lessons()->useWritePdo()->findOrFail($key);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(CourseResource::canEdit($this->ownerCourse), 403);
    }

    public function form(Schema $schema): Schema
    {
        return CourseLessonForm::configure($schema, $this->ownerCourse);
    }

    public function getRelationManagers(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->getResourceBreadcrumbs(),
            $this->getRedirectUrl() => $this->ownerCourse->title.' — درس‌ها',
            $this->getBreadcrumb(),
        ];
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->alpineClickHandler(null)
            ->url($this->getRedirectUrl());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('lessons', ['record' => $this->ownerCourse]);
    }
}

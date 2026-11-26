<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Filament\Resources\Courses\Schemas\CourseLessonForm;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseLesson;

/** @extends CreateRecord<CourseLesson> */
class CreateCourseLesson extends CreateRecord
{
    protected static string $resource = CourseResource::class;

    protected static ?string $title = 'ایجاد درس';

    protected static ?string $breadcrumb = 'ایجاد درس';

    protected ?bool $hasDatabaseTransactions = true;

    #[Locked]
    public Course $ownerCourse;

    public function mount(int|string|null $course = null): void
    {
        abort_if($course === null, 404);

        $this->ownerCourse = $this->resolveRecord($course);

        parent::mount();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(CourseResource::canEdit($this->ownerCourse), 403);
    }

    public function getModel(): string
    {
        return CourseLesson::class;
    }

    protected function resolveRecordFromLivewire(Model $model): CourseLesson
    {
        return $this->ownerCourse->lessons()->findOrFail($model->getKey());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات درس')->columns(2)->columnSpanFull()->schema(CourseLessonForm::fields($this->ownerCourse)),
        ]);
    }

    protected function handleRecordCreation(array $data): CourseLesson
    {
        return $this->ownerCourse->lessons()->create($data);
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

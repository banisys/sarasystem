<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Filament\Resources\Pages\Concerns\HasRelationManagers;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Modules\Course\Filament\Resources\Courses\CourseResource;
use Modules\Course\Models\Course;

abstract class ManageCourseContent extends Page
{
    use HasRelationManagers;
    use InteractsWithRecord;

    protected static string $resource = CourseResource::class;

    /** @var class-string<RelationManager> */
    protected static string $relationManager;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public static function canAccess(array $parameters = []): bool
    {
        $course = $parameters['record'] ?? null;

        return $course instanceof Course
            && CourseResource::canEdit($course);
    }

    public function getTitle(): string
    {
        return $this->getBreadcrumb().' — '.$this->getRecord()->title;
    }

    protected function getAllRelationManagers(): array
    {
        return [static::$relationManager];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([$this->getRelationManagersContentComponent()]);
    }
}

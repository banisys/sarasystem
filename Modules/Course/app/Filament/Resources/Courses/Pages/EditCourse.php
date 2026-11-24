<?php

namespace Modules\Course\Filament\Resources\Courses\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Course\Filament\Resources\Courses\CourseResource;

class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => ! $this->getRecord()->lessons()->exists()),
        ];
    }
}

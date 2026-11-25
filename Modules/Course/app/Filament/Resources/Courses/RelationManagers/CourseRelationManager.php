<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Modules\Course\Models\Course;

abstract class CourseRelationManager extends RelationManager
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Course && Gate::allows('update', $ownerRecord);
    }

    public function booted(): void
    {
        Gate::authorize('update', $this->course());
    }

    protected function course(): Course
    {
        return $this->getOwnerRecord();
    }
}

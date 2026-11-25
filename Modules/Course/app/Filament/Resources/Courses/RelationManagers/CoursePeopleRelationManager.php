<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

abstract class CoursePeopleRelationManager extends CourseRelationManager
{
    protected function userSelect(string $label): Select
    {
        $eligibleUsers = fn (Builder $query): Builder => $this->eligibleUsers($query);

        return Select::make('user_id')->label($label)->required()
            ->relationship('user', 'name', modifyQueryUsing: $eligibleUsers)
            ->searchable()
            ->scopedExists(User::class, 'id', $eligibleUsers)
            ->scopedUnique(modifyQueryUsing: fn (Builder $query): Builder => $query->where('course_id', $this->course()->getKey()));
    }

    protected function eligibleUsers(Builder $query): Builder
    {
        return $query;
    }
}

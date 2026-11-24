<?php

namespace Modules\Course\Filament\Resources\Courses\RelationManagers;

use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

abstract class CoursePeopleRelationManager extends CourseRelationManager
{
    protected function memberSelect(string $label): Select
    {
        $eligibleMembers = fn (Builder $query): Builder => $this->eligibleMembers($query->inTenant(Tenant::current()));

        return Select::make('user_id')->label($label)->required()
            ->relationship('user', 'name', modifyQueryUsing: $eligibleMembers)
            ->searchable()
            ->scopedExists(User::class, 'id', $eligibleMembers)
            ->scopedUnique(modifyQueryUsing: fn (Builder $query): Builder => $query->where('course_id', $this->course()->getKey()));
    }

    protected function eligibleMembers(Builder $query): Builder
    {
        return $query;
    }
}

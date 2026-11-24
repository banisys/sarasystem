<?php

namespace Modules\Course\Policies;

use App\Models\User;
use Modules\Course\Models\CourseCategory;

class CourseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:CourseCategory');
    }

    public function view(User $user, CourseCategory $record): bool
    {
        return $user->can('View:CourseCategory');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:CourseCategory');
    }

    public function update(User $user, CourseCategory $record): bool
    {
        return $user->can('Update:CourseCategory');
    }

    public function delete(User $user, CourseCategory $record): bool
    {
        return $user->can('Delete:CourseCategory');
    }
}

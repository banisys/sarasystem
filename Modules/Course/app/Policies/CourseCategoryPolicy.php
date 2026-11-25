<?php

namespace Modules\Course\Policies;

use App\Models\User;
use Modules\Course\Models\CourseCategory;
use Modules\Course\Support\CoursePermissions;

class CourseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return CoursePermissions::allows($user, 'ViewAny:CourseCategory');
    }

    public function view(User $user, CourseCategory $record): bool
    {
        return CoursePermissions::allows($user, 'View:CourseCategory');
    }

    public function create(User $user): bool
    {
        return CoursePermissions::allows($user, 'Create:CourseCategory');
    }

    public function update(User $user, CourseCategory $record): bool
    {
        return CoursePermissions::allows($user, 'Update:CourseCategory');
    }

    public function delete(User $user, CourseCategory $record): bool
    {
        return CoursePermissions::allows($user, 'Delete:CourseCategory');
    }
}

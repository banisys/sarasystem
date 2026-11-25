<?php

namespace Modules\Course\Policies;

use App\Models\User;
use Modules\Course\Models\Course;
use Modules\Course\Support\CoursePermissions;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return CoursePermissions::allows($user, 'ViewAny:Course');
    }

    public function view(User $user, Course $record): bool
    {
        return CoursePermissions::allows($user, 'View:Course');
    }

    public function create(User $user): bool
    {
        return CoursePermissions::allows($user, 'Create:Course');
    }

    public function update(User $user, Course $record): bool
    {
        return CoursePermissions::allows($user, 'Update:Course');
    }

    public function delete(User $user, Course $record): bool
    {
        return CoursePermissions::allows($user, 'Delete:Course');
    }
}

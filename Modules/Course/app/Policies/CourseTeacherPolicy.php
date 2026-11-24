<?php

namespace Modules\Course\Policies;

use App\Models\User;
use Modules\Course\Models\CourseTeacher;

class CourseTeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:CourseTeacher');
    }

    public function view(User $user, CourseTeacher $record): bool
    {
        return $user->can('View:CourseTeacher');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:CourseTeacher');
    }

    public function update(User $user, CourseTeacher $record): bool
    {
        return $user->can('Update:CourseTeacher');
    }

    public function delete(User $user, CourseTeacher $record): bool
    {
        return $user->can('Delete:CourseTeacher');
    }
}

<?php

namespace Modules\Course\Policies;

use App\Models\User;
use Modules\Course\Models\CourseTeacher;
use Modules\Course\Support\CoursePermissions;

class CourseTeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return CoursePermissions::allows($user, 'ViewAny:CourseTeacher');
    }

    public function view(User $user, CourseTeacher $record): bool
    {
        return CoursePermissions::allows($user, 'View:CourseTeacher');
    }

    public function create(User $user): bool
    {
        return CoursePermissions::allows($user, 'Create:CourseTeacher');
    }

    public function update(User $user, CourseTeacher $record): bool
    {
        return CoursePermissions::allows($user, 'Update:CourseTeacher');
    }

    public function delete(User $user, CourseTeacher $record): bool
    {
        return CoursePermissions::allows($user, 'Delete:CourseTeacher');
    }
}

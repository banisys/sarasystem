<?php

namespace Modules\Course\Policies;

use App\Models\User;
use Modules\Course\Models\Course;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Course');
    }

    public function view(User $user, Course $record): bool
    {
        return $user->can('View:Course');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Course');
    }

    public function update(User $user, Course $record): bool
    {
        return $user->can('Update:Course');
    }

    public function delete(User $user, Course $record): bool
    {
        return $user->can('Delete:Course');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Exam\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Exam\Models\ExamGroup;

class ExamGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExamGroup');
    }

    public function view(AuthUser $authUser, ExamGroup $examGroup): bool
    {
        return $authUser->can('View:ExamGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExamGroup');
    }

    public function update(AuthUser $authUser, ExamGroup $examGroup): bool
    {
        return $authUser->can('Update:ExamGroup');
    }

    public function delete(AuthUser $authUser, ExamGroup $examGroup): bool
    {
        return $authUser->can('Delete:ExamGroup');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ExamGroup');
    }

    public function restore(AuthUser $authUser, ExamGroup $examGroup): bool
    {
        return $authUser->can('Restore:ExamGroup');
    }

    public function forceDelete(AuthUser $authUser, ExamGroup $examGroup): bool
    {
        return $authUser->can('ForceDelete:ExamGroup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ExamGroup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ExamGroup');
    }

    public function replicate(AuthUser $authUser, ExamGroup $examGroup): bool
    {
        return $authUser->can('Replicate:ExamGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ExamGroup');
    }
}

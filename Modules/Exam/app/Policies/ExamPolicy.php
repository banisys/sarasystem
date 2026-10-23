<?php

declare(strict_types=1);

namespace Modules\Exam\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Exam\Models\Exam;

class ExamPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Exam');
    }

    public function view(AuthUser $authUser, Exam $exam): bool
    {
        return $authUser->can('View:Exam');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Exam');
    }

    public function update(AuthUser $authUser, Exam $exam): bool
    {
        return $authUser->can('Update:Exam');
    }

    public function delete(AuthUser $authUser, Exam $exam): bool
    {
        return $authUser->can('Delete:Exam');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Exam');
    }

    public function restore(AuthUser $authUser, Exam $exam): bool
    {
        return $authUser->can('Restore:Exam');
    }

    public function forceDelete(AuthUser $authUser, Exam $exam): bool
    {
        return $authUser->can('ForceDelete:Exam');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Exam');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Exam');
    }

    public function replicate(AuthUser $authUser, Exam $exam): bool
    {
        return $authUser->can('Replicate:Exam');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Exam');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Exam\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Exam\Models\QuestionTag;

class QuestionTagPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:QuestionTag');
    }

    public function view(AuthUser $authUser, QuestionTag $questionTag): bool
    {
        return $authUser->can('View:QuestionTag');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:QuestionTag');
    }

    public function update(AuthUser $authUser, QuestionTag $questionTag): bool
    {
        return $authUser->can('Update:QuestionTag');
    }

    public function delete(AuthUser $authUser, QuestionTag $questionTag): bool
    {
        return $authUser->can('Delete:QuestionTag');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:QuestionTag');
    }

    public function restore(AuthUser $authUser, QuestionTag $questionTag): bool
    {
        return $authUser->can('Restore:QuestionTag');
    }

    public function forceDelete(AuthUser $authUser, QuestionTag $questionTag): bool
    {
        return $authUser->can('ForceDelete:QuestionTag');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:QuestionTag');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:QuestionTag');
    }

    public function replicate(AuthUser $authUser, QuestionTag $questionTag): bool
    {
        return $authUser->can('Replicate:QuestionTag');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:QuestionTag');
    }
}

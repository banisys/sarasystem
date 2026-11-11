<?php

declare(strict_types=1);

namespace Modules\Exam\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Exam\Models\QuestionCategory;

class QuestionCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:QuestionCategory');
    }

    public function view(AuthUser $authUser, QuestionCategory $questionCategory): bool
    {
        return $authUser->can('View:QuestionCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:QuestionCategory');
    }

    public function update(AuthUser $authUser, QuestionCategory $questionCategory): bool
    {
        return $authUser->can('Update:QuestionCategory');
    }

    public function delete(AuthUser $authUser, QuestionCategory $questionCategory): bool
    {
        return $authUser->can('Delete:QuestionCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:QuestionCategory');
    }

    public function restore(AuthUser $authUser, QuestionCategory $questionCategory): bool
    {
        return $authUser->can('Restore:QuestionCategory');
    }

    public function forceDelete(AuthUser $authUser, QuestionCategory $questionCategory): bool
    {
        return $authUser->can('ForceDelete:QuestionCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:QuestionCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:QuestionCategory');
    }

    public function replicate(AuthUser $authUser, QuestionCategory $questionCategory): bool
    {
        return $authUser->can('Replicate:QuestionCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:QuestionCategory');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Exam\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Exam\Models\ExamAttempt;

class ExamAttemptPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExamAttempt');
    }

    public function view(AuthUser $authUser, ExamAttempt $examAttempt): bool
    {
        return $authUser->can('View:ExamAttempt');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExamAttempt');
    }

    public function update(AuthUser $authUser, ExamAttempt $examAttempt): bool
    {
        return $authUser->can('Update:ExamAttempt');
    }

    public function delete(AuthUser $authUser, ExamAttempt $examAttempt): bool
    {
        return $authUser->can('Delete:ExamAttempt');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ExamAttempt');
    }

    public function restore(AuthUser $authUser, ExamAttempt $examAttempt): bool
    {
        return $authUser->can('Restore:ExamAttempt');
    }

    public function forceDelete(AuthUser $authUser, ExamAttempt $examAttempt): bool
    {
        return $authUser->can('ForceDelete:ExamAttempt');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ExamAttempt');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ExamAttempt');
    }

    public function replicate(AuthUser $authUser, ExamAttempt $examAttempt): bool
    {
        return $authUser->can('Replicate:ExamAttempt');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ExamAttempt');
    }
}

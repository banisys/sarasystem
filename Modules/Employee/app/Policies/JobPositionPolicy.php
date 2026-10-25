<?php

declare(strict_types=1);

namespace Modules\Employee\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Employee\Models\JobPosition;

class JobPositionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JobPosition');
    }

    public function view(AuthUser $authUser, JobPosition $jobPosition): bool
    {
        return $authUser->can('View:JobPosition');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JobPosition');
    }

    public function update(AuthUser $authUser, JobPosition $jobPosition): bool
    {
        return $authUser->can('Update:JobPosition');
    }

    public function delete(AuthUser $authUser, JobPosition $jobPosition): bool
    {
        return $authUser->can('Delete:JobPosition');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JobPosition');
    }

    public function restore(AuthUser $authUser, JobPosition $jobPosition): bool
    {
        return $authUser->can('Restore:JobPosition');
    }

    public function forceDelete(AuthUser $authUser, JobPosition $jobPosition): bool
    {
        return $authUser->can('ForceDelete:JobPosition');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JobPosition');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JobPosition');
    }

    public function replicate(AuthUser $authUser, JobPosition $jobPosition): bool
    {
        return $authUser->can('Replicate:JobPosition');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JobPosition');
    }
}

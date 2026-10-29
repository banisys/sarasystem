<?php

declare(strict_types=1);

namespace Modules\Class\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Class\Models\ClassSession;

class ClassSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClassSession');
    }

    public function view(AuthUser $authUser, ClassSession $classSession): bool
    {
        return $authUser->can('View:ClassSession');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClassSession');
    }

    public function update(AuthUser $authUser, ClassSession $classSession): bool
    {
        return $authUser->can('Update:ClassSession');
    }

    public function delete(AuthUser $authUser, ClassSession $classSession): bool
    {
        return $authUser->can('Delete:ClassSession');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ClassSession');
    }

    public function restore(AuthUser $authUser, ClassSession $classSession): bool
    {
        return $authUser->can('Restore:ClassSession');
    }

    public function forceDelete(AuthUser $authUser, ClassSession $classSession): bool
    {
        return $authUser->can('ForceDelete:ClassSession');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ClassSession');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ClassSession');
    }

    public function replicate(AuthUser $authUser, ClassSession $classSession): bool
    {
        return $authUser->can('Replicate:ClassSession');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ClassSession');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Class\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Class\Models\ClassRole;

class ClassRolePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClassRole');
    }

    public function view(AuthUser $authUser, ClassRole $classRole): bool
    {
        return $authUser->can('View:ClassRole');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClassRole');
    }

    public function update(AuthUser $authUser, ClassRole $classRole): bool
    {
        return $authUser->can('Update:ClassRole');
    }

    public function delete(AuthUser $authUser, ClassRole $classRole): bool
    {
        return $authUser->can('Delete:ClassRole');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ClassRole');
    }

    public function restore(AuthUser $authUser, ClassRole $classRole): bool
    {
        return $authUser->can('Restore:ClassRole');
    }

    public function forceDelete(AuthUser $authUser, ClassRole $classRole): bool
    {
        return $authUser->can('ForceDelete:ClassRole');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ClassRole');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ClassRole');
    }

    public function replicate(AuthUser $authUser, ClassRole $classRole): bool
    {
        return $authUser->can('Replicate:ClassRole');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ClassRole');
    }
}

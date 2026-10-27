<?php

declare(strict_types=1);

namespace Modules\Student\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Student\Models\Preregistration;

class PreregistrationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Preregistration');
    }

    public function view(AuthUser $authUser, Preregistration $preregistration): bool
    {
        return $authUser->can('View:Preregistration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Preregistration');
    }

    public function update(AuthUser $authUser, Preregistration $preregistration): bool
    {
        return $authUser->can('Update:Preregistration');
    }

    public function delete(AuthUser $authUser, Preregistration $preregistration): bool
    {
        return $authUser->can('Delete:Preregistration');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Preregistration');
    }

    public function restore(AuthUser $authUser, Preregistration $preregistration): bool
    {
        return $authUser->can('Restore:Preregistration');
    }

    public function forceDelete(AuthUser $authUser, Preregistration $preregistration): bool
    {
        return $authUser->can('ForceDelete:Preregistration');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Preregistration');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Preregistration');
    }

    public function replicate(AuthUser $authUser, Preregistration $preregistration): bool
    {
        return $authUser->can('Replicate:Preregistration');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Preregistration');
    }
}

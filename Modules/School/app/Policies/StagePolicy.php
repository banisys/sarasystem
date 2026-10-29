<?php

declare(strict_types=1);

namespace Modules\School\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\School\Models\Stage;

class StagePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Stage');
    }

    public function view(AuthUser $authUser, Stage $stage): bool
    {
        return $authUser->can('View:Stage');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Stage');
    }

    public function update(AuthUser $authUser, Stage $stage): bool
    {
        return $authUser->can('Update:Stage');
    }

    public function delete(AuthUser $authUser, Stage $stage): bool
    {
        return $authUser->can('Delete:Stage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Stage');
    }

    public function restore(AuthUser $authUser, Stage $stage): bool
    {
        return $authUser->can('Restore:Stage');
    }

    public function forceDelete(AuthUser $authUser, Stage $stage): bool
    {
        return $authUser->can('ForceDelete:Stage');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Stage');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Stage');
    }

    public function replicate(AuthUser $authUser, Stage $stage): bool
    {
        return $authUser->can('Replicate:Stage');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Stage');
    }
}

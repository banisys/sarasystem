<?php

declare(strict_types=1);

namespace Modules\Class\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Class\Models\OnlineServer;

class OnlineServerPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OnlineServer');
    }

    public function view(AuthUser $authUser, OnlineServer $onlineServer): bool
    {
        return $authUser->can('View:OnlineServer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OnlineServer');
    }

    public function update(AuthUser $authUser, OnlineServer $onlineServer): bool
    {
        return $authUser->can('Update:OnlineServer');
    }

    public function delete(AuthUser $authUser, OnlineServer $onlineServer): bool
    {
        return $authUser->can('Delete:OnlineServer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OnlineServer');
    }

    public function restore(AuthUser $authUser, OnlineServer $onlineServer): bool
    {
        return $authUser->can('Restore:OnlineServer');
    }

    public function forceDelete(AuthUser $authUser, OnlineServer $onlineServer): bool
    {
        return $authUser->can('ForceDelete:OnlineServer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OnlineServer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OnlineServer');
    }

    public function replicate(AuthUser $authUser, OnlineServer $onlineServer): bool
    {
        return $authUser->can('Replicate:OnlineServer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OnlineServer');
    }
}

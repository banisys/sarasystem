<?php

declare(strict_types=1);

namespace Modules\Contract\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Contract\Models\ContractType;

class ContractTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ContractType');
    }

    public function view(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('View:ContractType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ContractType');
    }

    public function update(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('Update:ContractType');
    }

    public function delete(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('Delete:ContractType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ContractType');
    }

    public function restore(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('Restore:ContractType');
    }

    public function forceDelete(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('ForceDelete:ContractType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ContractType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ContractType');
    }

    public function replicate(AuthUser $authUser, ContractType $contractType): bool
    {
        return $authUser->can('Replicate:ContractType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ContractType');
    }
}

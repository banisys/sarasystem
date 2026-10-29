<?php

declare(strict_types=1);

namespace Modules\Class\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Class\Models\Term;

class TermPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Term');
    }

    public function view(AuthUser $authUser, Term $term): bool
    {
        return $authUser->can('View:Term');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Term');
    }

    public function update(AuthUser $authUser, Term $term): bool
    {
        return $authUser->can('Update:Term');
    }

    public function delete(AuthUser $authUser, Term $term): bool
    {
        return $authUser->can('Delete:Term');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Term');
    }

    public function restore(AuthUser $authUser, Term $term): bool
    {
        return $authUser->can('Restore:Term');
    }

    public function forceDelete(AuthUser $authUser, Term $term): bool
    {
        return $authUser->can('ForceDelete:Term');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Term');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Term');
    }

    public function replicate(AuthUser $authUser, Term $term): bool
    {
        return $authUser->can('Replicate:Term');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Term');
    }
}

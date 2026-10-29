<?php

declare(strict_types=1);

namespace Modules\Class\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Class\Models\TeachingPlan;

class TeachingPlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TeachingPlan');
    }

    public function view(AuthUser $authUser, TeachingPlan $teachingPlan): bool
    {
        return $authUser->can('View:TeachingPlan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TeachingPlan');
    }

    public function update(AuthUser $authUser, TeachingPlan $teachingPlan): bool
    {
        return $authUser->can('Update:TeachingPlan');
    }

    public function delete(AuthUser $authUser, TeachingPlan $teachingPlan): bool
    {
        return $authUser->can('Delete:TeachingPlan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TeachingPlan');
    }

    public function restore(AuthUser $authUser, TeachingPlan $teachingPlan): bool
    {
        return $authUser->can('Restore:TeachingPlan');
    }

    public function forceDelete(AuthUser $authUser, TeachingPlan $teachingPlan): bool
    {
        return $authUser->can('ForceDelete:TeachingPlan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TeachingPlan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TeachingPlan');
    }

    public function replicate(AuthUser $authUser, TeachingPlan $teachingPlan): bool
    {
        return $authUser->can('Replicate:TeachingPlan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TeachingPlan');
    }
}

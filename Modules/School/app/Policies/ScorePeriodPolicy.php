<?php

declare(strict_types=1);

namespace Modules\School\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\School\Models\ScorePeriod;

class ScorePeriodPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ScorePeriod');
    }

    public function view(AuthUser $authUser, ScorePeriod $scorePeriod): bool
    {
        return $authUser->can('View:ScorePeriod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ScorePeriod');
    }

    public function update(AuthUser $authUser, ScorePeriod $scorePeriod): bool
    {
        return $authUser->can('Update:ScorePeriod');
    }

    public function delete(AuthUser $authUser, ScorePeriod $scorePeriod): bool
    {
        return $authUser->can('Delete:ScorePeriod');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ScorePeriod');
    }

    public function restore(AuthUser $authUser, ScorePeriod $scorePeriod): bool
    {
        return $authUser->can('Restore:ScorePeriod');
    }

    public function forceDelete(AuthUser $authUser, ScorePeriod $scorePeriod): bool
    {
        return $authUser->can('ForceDelete:ScorePeriod');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ScorePeriod');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ScorePeriod');
    }

    public function replicate(AuthUser $authUser, ScorePeriod $scorePeriod): bool
    {
        return $authUser->can('Replicate:ScorePeriod');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ScorePeriod');
    }
}

<?php

namespace Modules\Course\Support;

use App\Models\Tenant;
use App\Models\User;

class CoursePermissions
{
    public static function allows(User $user, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $primary = once(fn (): ?Tenant => Tenant::primary());

        if ($primary?->isCurrent()) {
            return $user->can($permission);
        }

        return $primary?->execute(fn (): bool => (clone $user)
            ->unsetRelation('roles')->unsetRelation('permissions')->can($permission)) ?? false;
    }
}

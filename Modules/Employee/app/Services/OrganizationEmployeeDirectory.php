<?php

namespace Modules\Employee\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Support\EmployeeDirectory;
use Illuminate\Database\Eloquent\Builder;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Models\Employee;

class OrganizationEmployeeDirectory implements EmployeeDirectory
{
    public function users(): Builder
    {
        return User::query()->whereIn('id', Employee::withoutTenantScope()->select('user_id'));
    }

    public function employs(User $user, Tenant $tenant): bool
    {
        return Employee::withoutTenantScope()->whereBelongsTo($tenant)->whereBelongsTo($user)
            ->where('status', EmploymentStatus::Active)->exists();
    }
}

<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

interface EmployeeDirectory
{
    /** @return Builder<User> Users with an employment record in any tenant. */
    public function users(): Builder;

    public function employs(User $user, Tenant $tenant): bool;
}

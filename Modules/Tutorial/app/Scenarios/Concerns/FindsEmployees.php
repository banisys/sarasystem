<?php

namespace Modules\Tutorial\Scenarios\Concerns;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Modules\Employee\Models\Employee;
use Modules\Tutorial\Models\TutorialRun;

/**
 * Finding the people a scenario hired or entered as employees.
 */
trait FindsEmployees
{
    /**
     * The user of an employee made in the run who holds the role in the
     * tenant (and, withPanel, may use its admin panel).
     */
    protected static function employeeIn(TutorialRun $run, ?Tenant $tenant, string $roleName, bool $withPanel = false): ?User
    {
        $role = Role::query()->whereNull('tenant_id')->where('name', $roleName)->first();

        if ($tenant === null || $role === null) {
            return null;
        }

        return $run->made(Employee::class)->where('tenant_id', $tenant->getKey())->with('user')->get()
            ->map(fn (Employee $employee): ?User => $employee->user)
            ->first(fn (?User $user): bool => $user !== null
                && in_array($role->getKey(), $user->roleIdsIn($tenant))
                && (! $withPanel || $user->staffTenants()->whereKey($tenant->getKey())->exists()));
    }
}

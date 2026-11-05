<?php

namespace Modules\Employee\Services;

use App\Campus\StaffSource;
use App\Models\User;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Filament\Resources\Employees\EmployeeResource;
use Modules\Employee\Models\Employee;

/**
 * The current tenant's active employees, for the staff pyramid (ADR 0058).
 */
class EmployeePeople implements StaffSource
{
    public function canView(User $user): bool
    {
        return $user->can('viewAny', Employee::class);
    }

    public function people(User $user): array
    {
        $employees = Employee::query()->with('user:id,name')->where('status', EmploymentStatus::Active)->get();
        $canOpen = $employees->isNotEmpty() && $user->can('view', $employees->first());

        return $employees->map(fn (Employee $employee): array => [
            'user_id' => $employee->user_id,
            'name' => $employee->user->name,
            'title' => $employee->job_title,
            'url' => $canOpen ? EmployeeResource::getUrl('view', ['record' => $employee]) : null,
        ])->values()->all();
    }
}

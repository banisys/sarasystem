<?php

namespace Modules\Employee\Services;

use App\Authorization\RoleAssignment;
use App\Documents\ApplicantDocuments;
use App\Events\ApplicantAccepted;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Workflow\WorkflowActionDenied;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Support\Str;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;

/**
 * The last step of hiring (ADR 0016): the applicant becomes a user (or an
 * existing user with the same national code is reused), gets roles in the
 * chosen tenant and an employee row there, and the application is closed
 * as hired. Runs inside the workflow engine's transaction.
 */
class HireApplicant
{
    /**
     * @param  array{tenant_id: int|string, role_ids?: list<int|string>, panel_access?: bool, personnel_code?: string|null, employment_type: EmploymentType|string, hired_at: string}  $data
     */
    public function hire(JobApplication $application, User $actor, array $data): Employee
    {
        $tenant = Tenant::query()->active()->find($data['tenant_id'])
            ?? throw new WorkflowActionDenied('محل خدمت انتخاب‌شده فعال نیست.');

        $roles = $this->roles($actor, $tenant, $data['role_ids'] ?? [], (bool) ($data['panel_access'] ?? false));
        $user = $this->user($application, $actor, $tenant);

        if (Employee::withoutTenantScope()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->exists()) {
            throw new WorkflowActionDenied("{$user->name} از قبل کارمند {$tenant->name} است.");
        }

        if (filled($data['personnel_code'] ?? null) && Employee::withoutTenantScope()->where('personnel_code', $data['personnel_code'])->exists()) {
            throw new WorkflowActionDenied('این کد پرسنلی قبلاً به کارمند دیگری داده شده است.');
        }

        return $tenant->execute(function () use ($tenant, $user, $roles, $application, $data, $actor): Employee {
            if ($roles !== []) {
                $user->unsetRelation('roles')->assignRole($roles);
                $user->unsetRelation('roles');
            }

            $employee = Employee::query()->create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'job_application_id' => $application->getKey(),
                'job_title' => $application->job_title,
                'personnel_code' => filled($data['personnel_code'] ?? null) ? $data['personnel_code'] : null,
                'employment_type' => $data['employment_type'] instanceof EmploymentType ? $data['employment_type'] : EmploymentType::from($data['employment_type']),
                'status' => EmploymentStatus::Active,
                'hired_at' => $data['hired_at'],
            ]);

            $application->update(['status' => ApplicationStatus::Hired]);

            // The applicant's documents stay with the employee (ADR 0019).
            app(ApplicantDocuments::class)->accepted($application, $employee);

            ApplicantAccepted::dispatch($application, $employee, $actor);

            return $employee;
        });
    }

    /**
     * The applicant's account: the user with their national code, or a new
     * one. An existing user is only reused when the actor may manage them
     * (ADR 0003); their identity fields are left as they are.
     */
    private function user(JobApplication $application, User $actor, Tenant $tenant): User
    {
        $existing = User::query()->where('national_code', $application->national_code)->first();

        if ($existing !== null) {
            if (! RoleAssignment::canManage($actor, $existing, $tenant)) {
                throw new WorkflowActionDenied("کاربری با این کد ملی ({$existing->name}) در سامانه هست که شما اجازه‌ی مدیریت او را ندارید.");
            }

            return $existing;
        }

        $user = new User([
            'name' => $application->fullName(),
            'national_code' => $application->national_code,
            'mobile' => $application->mobile,
            // Email is unique; someone else's address is left out. The form may not ask it (ADR 0017).
            'email' => blank($application->email) || User::query()->where('email', Str::lower($application->email))->exists() ? null : $application->email,
            'password' => Str::password(32),
        ]);
        // The mobile was confirmed by SMS in the «همکاری با ما» form.
        $user->forceFill(['mobile_verified_at' => $application->created_at ?? now()])->save();

        return $user;
    }

    /**
     * @param  list<int|string>  $roleIds
     * @return list<Role>
     */
    private function roles(User $actor, Tenant $tenant, array $roleIds, bool $panelAccess): array
    {
        $assignable = RoleAssignment::assignableRoles($actor, $tenant)->keyBy('id');
        $roleIds = array_map('intval', $roleIds);

        if ($panelAccess) {
            $panelRole = Role::query()->whereNull('tenant_id')->where('name', Utils::getPanelUserRoleName())->first();
            $roleIds[] = $panelRole?->getKey() ?? throw new WorkflowActionDenied('نقش دسترسی به پنل تعریف نشده است.');
        }

        $roleIds = array_values(array_unique($roleIds));

        foreach ($roleIds as $roleId) {
            if (! $assignable->has($roleId)) {
                throw new WorkflowActionDenied('شما اجازه‌ی دادن یکی از نقش‌های انتخاب‌شده را در '.$tenant->name.' ندارید.');
            }
        }

        return $assignable->only($roleIds)->values()->all();
    }
}

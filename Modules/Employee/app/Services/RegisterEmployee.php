<?php

namespace Modules\Employee\Services;

use App\Authorization\RoleAssignment;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Identifiers;
use BezhanSalleh\FilamentShield\Support\Utils;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Models\Employee;

/**
 * Makes a person an employee of a tenant (ADR 0016, ADR 0038): the user
 * with their national code, or a new one, gets the roles the actor may give
 * there and an active employee row. Shared by hiring (HireApplicant), the
 * «ثبت سریع کارمند» form and the employee import.
 *
 * An existing user is only reused when the actor may manage them (ADR 0003);
 * their identity fields are left as they are. A new user gets a random
 * password and signs in with an SMS code to their mobile (ADR 0004).
 */
class RegisterEmployee
{
    /**
     * @param  array{name: string, national_code: string, mobile?: string|null, email?: string|null, mobile_verified_at?: CarbonInterface|null}  $person
     * @param  array{job_title: string, personnel_code?: string|null, employment_type: EmploymentType|string, hired_at: CarbonInterface|string, job_application_id?: int|null}  $employment
     * @param  list<int|string>  $roleIds
     *
     * @throws EmployeeException
     */
    public function register(User $actor, Tenant $tenant, array $person, array $employment, array $roleIds = [], bool $panelAccess = false): Employee
    {
        if (! $tenant->is_active) {
            throw new EmployeeException("{$tenant->name} فعال نیست.");
        }

        $roles = $this->roles($actor, $tenant, $roleIds, $panelAccess);
        $nationalCode = Identifiers::toLatinDigits(trim($person['national_code']));
        $existing = User::query()->where('national_code', $nationalCode)->first();

        if ($existing !== null) {
            if (! RoleAssignment::canManage($actor, $existing, $tenant)) {
                throw new EmployeeException("کاربری با این کد ملی ({$existing->name}) در سامانه هست که شما اجازه‌ی مدیریت او را ندارید.");
            }

            if (Employee::withoutTenantScope()->where('tenant_id', $tenant->getKey())->where('user_id', $existing->getKey())->exists()) {
                throw new EmployeeException("{$existing->name} از قبل کارمند {$tenant->name} است.");
            }
        }

        $personnelCode = filled($employment['personnel_code'] ?? null) ? Identifiers::toLatinDigits(trim($employment['personnel_code'])) : null;

        if ($personnelCode !== null && Employee::withoutTenantScope()->where('personnel_code', $personnelCode)->exists()) {
            throw new EmployeeException('این کد پرسنلی قبلاً به کارمند دیگری داده شده است.');
        }

        // A savepoint when called inside a transaction (workflow engine, import chunk).
        return DB::transaction(fn (): Employee => $tenant->execute(function () use ($tenant, $existing, $person, $nationalCode, $roles, $employment, $personnelCode): Employee {
            $user = $existing ?? $this->newUser($person, $nationalCode);

            if ($roles !== []) {
                $user->unsetRelation('roles')->assignRole($roles);
                $user->unsetRelation('roles');
            }

            $employee = Employee::query()->create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'job_application_id' => $employment['job_application_id'] ?? null,
                'job_title' => trim($employment['job_title']),
                'personnel_code' => $personnelCode,
                'employment_type' => $employment['employment_type'] instanceof EmploymentType ? $employment['employment_type'] : EmploymentType::from($employment['employment_type']),
                'status' => EmploymentStatus::Active,
                'hired_at' => $employment['hired_at'],
            ]);

            return $employee->setRelation('user', $user);
        }));
    }

    /**
     * @param  array{name: string, mobile?: string|null, email?: string|null, mobile_verified_at?: CarbonInterface|null}  $person
     */
    private function newUser(array $person, string $nationalCode): User
    {
        $email = $person['email'] ?? null;

        $user = new User([
            'name' => trim($person['name']),
            'national_code' => $nationalCode,
            'mobile' => $person['mobile'] ?? null,
            // Email is unique; someone else's address is left out.
            'email' => blank($email) || User::query()->where('email', Str::lower($email))->exists() ? null : $email,
            'password' => Str::password(32),
        ]);
        $user->forceFill(['mobile_verified_at' => $person['mobile_verified_at'] ?? null])->save();

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
            $roleIds[] = $panelRole?->getKey() ?? throw new EmployeeException('نقش دسترسی به پنل تعریف نشده است.');
        }

        $roleIds = array_values(array_unique($roleIds));

        foreach ($roleIds as $roleId) {
            if (! $assignable->has($roleId)) {
                throw new EmployeeException('شما اجازه‌ی دادن یکی از نقش‌های انتخاب‌شده را در '.$tenant->name.' ندارید.');
            }
        }

        return $assignable->only($roleIds)->values()->all();
    }
}

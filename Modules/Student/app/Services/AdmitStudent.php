<?php

namespace Modules\Student\Services;

use App\Authorization\RoleAssignment;
use App\Documents\ApplicantDocuments;
use App\Events\ApplicantAccepted;
use App\Guardians\GuardianException;
use App\Guardians\Guardians;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Workflow\WorkflowActionDenied;
use Illuminate\Support\Str;
use Modules\Student\Enums\PreregistrationStatus;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Student;

/**
 * The last step of admission (ADR 0018): the applicant becomes a user (or
 * an existing user with the same national code is reused), gets roles in
 * the chosen tenant and a learner row there, the parents given in the form
 * become guardians (ADR 0028), and the preregistration is closed as
 * admitted. Runs inside the workflow engine's
 * transaction.
 */
class AdmitStudent
{
    /** The role offered by default, if it exists and the approver may give it. */
    public const STUDENT_ROLE = 'student';

    /**
     * @param  array{tenant_id: int|string, role_ids?: list<int|string>, student_code?: string|null, enrolled_at: string}  $data
     */
    public function admit(Preregistration $preregistration, User $actor, array $data): Student
    {
        $tenant = Tenant::query()->active()->find($data['tenant_id'])
            ?? throw new WorkflowActionDenied(config('sarasystem.tenant_label').' انتخاب‌شده فعال نیست.');

        $roles = $this->roles($actor, $tenant, $data['role_ids'] ?? []);
        $user = $this->user($preregistration, $actor, $tenant);

        if (Student::withoutTenantScope()->where('tenant_id', $tenant->getKey())->where('user_id', $user->getKey())->exists()) {
            throw new WorkflowActionDenied("{$user->name} از قبل ".config('sarasystem.learner_label')." {$tenant->name} است.");
        }

        if (filled($data['student_code'] ?? null) && Student::withoutTenantScope()->where('student_code', $data['student_code'])->exists()) {
            throw new WorkflowActionDenied('این کد '.config('sarasystem.learner_label').' قبلاً به شخص دیگری داده شده است.');
        }

        return $tenant->execute(function () use ($tenant, $user, $roles, $preregistration, $data, $actor): Student {
            if ($roles !== []) {
                $user->unsetRelation('roles')->assignRole($roles);
                $user->unsetRelation('roles');
            }

            $student = Student::query()->create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'preregistration_id' => $preregistration->getKey(),
                'student_code' => filled($data['student_code'] ?? null) ? $data['student_code'] : null,
                'status' => StudentStatus::Active,
                'enrolled_at' => $data['enrolled_at'],
            ]);

            $preregistration->update(['status' => PreregistrationStatus::Admitted]);

            // The parents given in the form become the learner's guardians (ADR 0028).
            $this->linkParents($preregistration, $user, $tenant, $actor);

            // The applicant's documents stay with the learner (ADR 0019).
            app(ApplicantDocuments::class)->accepted($preregistration, $student);

            ApplicantAccepted::dispatch($preregistration, $student, $actor);

            return $student;
        });
    }

    /**
     * The parents become guardians of the learner and get the guardian role
     * in the tenant. A new learner who signs in with a parent's mobile
     * follows it; an existing learner keeps their own mobile unless it is
     * that parent's.
     *
     * @throws WorkflowActionDenied
     */
    private function linkParents(Preregistration $preregistration, User $learner, Tenant $tenant, User $actor): void
    {
        $parents = $preregistration->parents();

        if ($parents !== [] && $learner->roleIdsIn($tenant) === []) {
            throw new WorkflowActionDenied('برای ثبت والدین، '.config('sarasystem.learner_label').' باید در '.$tenant->name.' نقشی داشته باشد.');
        }

        $guardians = app(Guardians::class);

        foreach ($parents as $parent) {
            try {
                $guardian = $guardians->findOrCreateGuardian($parent);
                $guardians->link(
                    $learner,
                    $guardian,
                    $parent['relationship'],
                    true,
                    $tenant,
                    $actor,
                    // A new learner's account follows the parent's mobile on record.
                    $parent['provides_login_mobile'] && ($learner->wasRecentlyCreated || $learner->mobile === $guardian->mobile),
                );
            } catch (GuardianException $exception) {
                throw new WorkflowActionDenied($exception->getMessage());
            }
        }
    }

    /**
     * The learner's account: the user with their national code, or a new
     * one. An existing user is only reused when the actor may manage them
     * (ADR 0003); their identity fields are left as they are.
     */
    private function user(Preregistration $preregistration, User $actor, Tenant $tenant): User
    {
        $existing = User::query()->where('national_code', $preregistration->national_code)->first();

        if ($existing !== null) {
            if (! RoleAssignment::canManage($actor, $existing, $tenant)) {
                throw new WorkflowActionDenied("کاربری با این کد ملی ({$existing->name}) در سامانه هست که شما اجازه‌ی مدیریت او را ندارید.");
            }

            return $existing;
        }

        $user = new User([
            'name' => $preregistration->fullName(),
            'national_code' => $preregistration->national_code,
            // Their own or a parent's (ADR 0028); mobiles are not unique (siblings share one).
            'mobile' => $preregistration->loginMobile(),
            // Email is unique; someone else's address is left out. The form may not ask it (ADR 0017).
            'email' => blank($preregistration->email) || User::query()->where('email', Str::lower($preregistration->email))->exists() ? null : $preregistration->email,
            'password' => Str::password(32),
        ]);
        // The mobile was confirmed by SMS in the «پیش‌ثبت‌نام» form.
        $user->forceFill(['mobile_verified_at' => $preregistration->created_at ?? now()])->save();

        return $user;
    }

    /**
     * @param  list<int|string>  $roleIds
     * @return list<Role>
     */
    private function roles(User $actor, Tenant $tenant, array $roleIds): array
    {
        $assignable = RoleAssignment::assignableRoles($actor, $tenant)->keyBy('id');
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        foreach ($roleIds as $roleId) {
            if (! $assignable->has($roleId)) {
                throw new WorkflowActionDenied('شما اجازه‌ی دادن یکی از نقش‌های انتخاب‌شده را در '.$tenant->name.' ندارید.');
            }
        }

        return $assignable->only($roleIds)->values()->all();
    }
}

<?php

namespace Modules\Employee\Services;

use App\Documents\ApplicantDocuments;
use App\Events\ApplicantAccepted;
use App\Models\Tenant;
use App\Models\User;
use App\Workflow\WorkflowActionDenied;
use Modules\Employee\Enums\ApplicationStatus;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;

/**
 * The last step of hiring (ADR 0016): the applicant becomes an employee of
 * the chosen tenant (RegisterEmployee: a new or reused user, roles and the
 * employee row), and the application is closed as hired. Runs inside the
 * workflow engine's transaction.
 */
class HireApplicant
{
    public function __construct(private RegisterEmployee $registrar) {}

    /**
     * @param  array{tenant_id: int|string, role_ids?: list<int|string>, panel_access?: bool, personnel_code?: string|null, employment_type: EmploymentType|string, hired_at: string}  $data
     */
    public function hire(JobApplication $application, User $actor, array $data): Employee
    {
        $tenant = Tenant::query()->active()->find($data['tenant_id'])
            ?? throw new WorkflowActionDenied('محل خدمت انتخاب‌شده فعال نیست.');

        try {
            $employee = $this->registrar->register(
                $actor,
                $tenant,
                person: [
                    'name' => $application->fullName(),
                    'national_code' => $application->national_code,
                    'mobile' => $application->mobile,
                    'email' => $application->email,
                    // The mobile was confirmed by SMS in the «همکاری با ما» form.
                    'mobile_verified_at' => $application->created_at ?? now(),
                ],
                employment: [
                    'job_application_id' => $application->getKey(),
                    'job_title' => $application->job_title,
                    'personnel_code' => $data['personnel_code'] ?? null,
                    'employment_type' => $data['employment_type'],
                    'hired_at' => $data['hired_at'],
                ],
                roleIds: $data['role_ids'] ?? [],
                panelAccess: (bool) ($data['panel_access'] ?? false),
            );
        } catch (EmployeeException $exception) {
            throw new WorkflowActionDenied($exception->getMessage(), previous: $exception);
        }

        return $tenant->execute(function () use ($application, $employee, $actor): Employee {
            $application->update(['status' => ApplicationStatus::Hired]);

            // The applicant's documents stay with the employee (ADR 0019).
            app(ApplicantDocuments::class)->accepted($application, $employee);

            ApplicantAccepted::dispatch($application, $employee, $actor);

            return $employee;
        });
    }
}

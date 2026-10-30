<?php

namespace Modules\Employee\Documents;

use App\CustomFields\CustomizableForm;
use App\Documents\DocumentHolder;
use App\Documents\DocumentHolders;
use App\Models\Tenant;
use App\Support\Identifiers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Employee\Forms\JobApplicationForm;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;
use Modules\Employee\Models\JobPosition;

/**
 * Employees' documents (ADR 0019): uploaded for a job application, kept
 * with the employee once hired, required per job position.
 */
class EmployeeDocumentHolder extends DocumentHolder
{
    public const KEY = 'employee';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'کارمند';
    }

    public function applicantModel(): string
    {
        return JobApplication::class;
    }

    public function memberModel(): string
    {
        return Employee::class;
    }

    public function form(): CustomizableForm
    {
        return new JobApplicationForm;
    }

    public function requirementOwnerModel(): string
    {
        return JobPosition::class;
    }

    public function requirementOwnerLabel(): string
    {
        return 'موقعیت‌های شغلی';
    }

    public function requirementOwnerOptions(Tenant $tenant): array
    {
        return JobPosition::withoutTenantScope()->where('tenant_id', $tenant->getKey())->ordered()->pluck('title', 'id')->all();
    }

    public function requirementOwnerId(Model $record): ?int
    {
        return match (true) {
            $record instanceof JobApplication => $record->job_position_id,
            $record instanceof Employee => $record->jobApplication()->withoutGlobalScopes()->value('job_position_id'),
            default => null,
        };
    }

    public function personName(Model $record): string
    {
        return match (true) {
            $record instanceof JobApplication => $record->fullName().' (متقاضی)',
            $record instanceof Employee => $record->user?->name ?? '—',
            default => '—',
        };
    }

    public function search(string $search): array
    {
        $search = Identifiers::toLatinDigits(trim($search));

        $applicants = JobApplication::query()->open()
            ->where(fn (Builder $query) => $query
                ->whereLike('first_name', "%{$search}%")
                ->orWhereLike('last_name', "%{$search}%")
                ->orWhereLike('national_code', "%{$search}%"))
            ->limit(10)
            ->get();

        $employees = Employee::query()->with('user')
            ->whereHas('user', fn (Builder $user) => $user->whereLike('name', "%{$search}%")->orWhereLike('national_code', "%{$search}%"))
            ->limit(10)
            ->get();

        return $applicants->concat($employees)
            ->mapWithKeys(fn (Model $record): array => [DocumentHolders::recordKey($record) => $this->personName($record)])
            ->all();
    }
}

<?php

namespace Modules\Employee\ContractParties;

use App\Contract\ContractParty;
use App\Models\User;
use App\Support\Identifiers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Employee\Models\Employee;
use Modules\Employee\Models\JobApplication;

/**
 * Employees' contracts (ADR 0020): prepared for a job application in the
 * hiring workflow, issued to the employee once hired. The person's data
 * comes from the user and, where the user has none, the application.
 */
class EmployeeContractParty extends ContractParty
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

    public function personName(Model $record): string
    {
        return match (true) {
            $record instanceof JobApplication => $record->fullName().' (متقاضی)',
            $record instanceof Employee => $record->user?->name ?? '—',
            default => '—',
        };
    }

    public function searchMembers(string $search): array
    {
        $search = Identifiers::toLatinDigits(trim($search));

        return Employee::query()->with('user')
            ->whereHas('user', fn (Builder $user) => $user->whereLike('name', "%{$search}%")->orWhereLike('national_code', "%{$search}%"))
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->getKey() => $this->personName($employee).' ('.($employee->user?->national_code ?? '—').')'])
            ->all();
    }

    public function user(Model $member): ?User
    {
        return $member instanceof Employee ? $member->user : null;
    }

    public function tags(): array
    {
        return [
            'job_title' => 'عنوان شغل',
            'personnel_code' => 'کد پرسنلی',
            'employment_type' => 'نوع همکاری',
            'hired_on' => 'تاریخ استخدام',
        ];
    }

    public function values(Model $record): array
    {
        $employee = $record instanceof Employee ? $record : null;
        $application = $record instanceof JobApplication ? $record : $employee?->jobApplication()->withoutGlobalScopes()->first();
        $user = $employee?->user;

        return [
            'person_name' => $user?->name ?? $application?->fullName(),
            'national_code' => $user?->national_code ?? $application?->national_code,
            'father_name' => $application?->father_name,
            'birth_date' => self::jalali($application?->birth_date),
            'mobile' => $user?->mobile ?? $application?->mobile,
            'address' => $application?->address,
            'job_title' => $employee?->job_title ?? $application?->job_title,
            'personnel_code' => $employee?->personnel_code,
            'employment_type' => $employee?->employment_type?->getLabel(),
            'hired_on' => self::jalali($employee?->hired_at),
        ];
    }
}

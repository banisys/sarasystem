<?php

namespace App\Documents;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of person who has documents (ADR 0019), described by the module
 * that owns them, e.g. employees (Employee) or learners (Student). A holder
 * has applicant records (a job application, a preregistration) whose
 * documents move to the member record (employee, learner) when the
 * applicant is accepted. Documents may be required per requirement owner
 * (a job position, a program).
 */
abstract class DocumentHolder
{
    /**
     * Stored in `documents.holder` and `document_types.holders`, e.g. `employee`.
     */
    abstract public function key(): string;

    /**
     * e.g. «کارمند».
     */
    abstract public function label(): string;

    /**
     * @return class-string<Model>
     */
    abstract public function applicantModel(): string;

    /**
     * @return class-string<Model>
     */
    abstract public function memberModel(): string;

    /**
     * What documents can be required for, e.g. «موقعیت شغلی».
     */
    abstract public function requirementOwnerLabel(): string;

    /**
     * The primary tenant's requirement owners (id => title), e.g. its job
     * positions.
     *
     * @return array<int, string>
     */
    abstract public function requirementOwnerOptions(Tenant $tenant): array;

    /**
     * The requirement owner of an applicant or member record, if any.
     */
    abstract public function requirementOwnerId(Model $record): ?int;

    /**
     * The person's name, for lists and titles.
     */
    abstract public function personName(Model $record): string;

    /**
     * Applicants and members of the current tenant matching a name or
     * national code, keyed by DocumentHolders::recordKey().
     *
     * @return array<string, string>
     */
    abstract public function search(string $search): array;

    /**
     * Whether the record is one of this holder's.
     */
    public function owns(Model $record): bool
    {
        return $record instanceof ($this->applicantModel()) || $record instanceof ($this->memberModel());
    }

    public function isApplicant(Model $record): bool
    {
        return $record instanceof ($this->applicantModel());
    }
}

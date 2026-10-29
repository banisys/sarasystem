<?php

namespace App\Contract;

use App\Models\User;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of person who signs contracts (ADR 0020), described by the module
 * that owns them, e.g. employees (Employee) or learners (Student). A
 * contract is prepared for an applicant record (a job application, a
 * preregistration) or a member record (employee, learner); an applicant's
 * contracts move to the member record when the applicant is accepted.
 */
abstract class ContractParty
{
    /**
     * Stored in `contracts.party` and `contract_types.parties`, e.g. `employee`.
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
     * The person's name, for lists and titles.
     */
    abstract public function personName(Model $record): string;

    /**
     * Members of the current tenant matching a name or national code,
     * keyed by record id.
     *
     * @return array<int, string>
     */
    abstract public function searchMembers(string $search): array;

    /**
     * The member's account, who signs the contract.
     */
    abstract public function user(Model $member): ?User;

    /**
     * Merge tags this party adds to contract texts, besides the person
     * tags: key => label.
     *
     * @return array<string, string>
     */
    public function tags(): array
    {
        return [];
    }

    /**
     * Values of the person tags and of this party's tags for an applicant
     * or member record; unknown values are null.
     *
     * @return array<string, string|null>
     */
    abstract public function values(Model $record): array;

    /**
     * Tags every party fills: key => label.
     *
     * @return array<string, string>
     */
    public static function personTags(): array
    {
        return [
            'person_name' => 'نام و نام خانوادگی',
            'national_code' => 'کد ملی',
            'father_name' => 'نام پدر',
            'birth_date' => 'تاریخ تولد',
            'mobile' => 'موبایل',
            'address' => 'نشانی',
        ];
    }

    /**
     * Whether the record is one of this party's.
     */
    public function owns(Model $record): bool
    {
        return $record instanceof ($this->applicantModel()) || $record instanceof ($this->memberModel());
    }

    public function isApplicant(Model $record): bool
    {
        return $record instanceof ($this->applicantModel());
    }

    /**
     * A date as contract texts print it, e.g. 1405/08/07.
     */
    protected static function jalali(?CarbonInterface $date): ?string
    {
        return $date === null ? null : CalendarUtils::strftime('Y/m/d', $date->copy()->toMutable());
    }
}

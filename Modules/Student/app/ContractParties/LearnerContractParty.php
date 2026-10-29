<?php

namespace Modules\Student\ContractParties;

use App\Contract\ContractParty;
use App\Models\User;
use App\Support\Identifiers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Student;

/**
 * Learners' contracts (ADR 0020), e.g. tuition or training: prepared for
 * a preregistration in the admission workflow, issued to the learner once
 * admitted. The person's data comes from the user and, where the user has
 * none, the preregistration.
 */
class LearnerContractParty extends ContractParty
{
    public const KEY = 'learner';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return config('sarasystem.learner_label');
    }

    public function applicantModel(): string
    {
        return Preregistration::class;
    }

    public function memberModel(): string
    {
        return Student::class;
    }

    public function personName(Model $record): string
    {
        return match (true) {
            $record instanceof Preregistration => $record->fullName().' (متقاضی)',
            $record instanceof Student => $record->user?->name ?? '—',
            default => '—',
        };
    }

    public function searchMembers(string $search): array
    {
        $search = Identifiers::toLatinDigits(trim($search));

        return Student::query()->with('user')
            ->whereHas('user', fn (Builder $user) => $user->whereLike('name', "%{$search}%")->orWhereLike('national_code', "%{$search}%"))
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [$student->getKey() => $this->personName($student).' ('.($student->user?->national_code ?? '—').')'])
            ->all();
    }

    public function user(Model $member): ?User
    {
        return $member instanceof Student ? $member->user : null;
    }

    public function tags(): array
    {
        return [
            'program' => 'برنامه‌ی آموزشی',
            'student_code' => 'کد '.config('sarasystem.learner_label'),
            'enrolled_on' => 'تاریخ پذیرش',
        ];
    }

    public function values(Model $record): array
    {
        $student = $record instanceof Student ? $record : null;
        $preregistration = $record instanceof Preregistration ? $record : $student?->preregistration;
        $user = $student?->user;

        return [
            'person_name' => $user?->name ?? $preregistration?->fullName(),
            'national_code' => $user?->national_code ?? $preregistration?->national_code,
            'father_name' => $preregistration?->father_name,
            'birth_date' => self::jalali($preregistration?->birth_date),
            'mobile' => $user?->mobile ?? $preregistration?->mobile,
            'address' => collect([$preregistration?->city, $preregistration?->address])->filter()->implode('، ') ?: null,
            'program' => $preregistration?->program_title,
            'student_code' => $student?->student_code,
            'enrolled_on' => self::jalali($student?->enrolled_at),
        ];
    }
}

<?php

namespace Modules\Student\Documents;

use App\Documents\DocumentHolder;
use App\Documents\DocumentHolders;
use App\Models\Tenant;
use App\Support\Identifiers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Student\Models\Preregistration;
use Modules\Student\Models\Program;
use Modules\Student\Models\Student;

/**
 * Learners' documents (ADR 0019): uploaded for a preregistration, kept
 * with the learner once admitted, required per program.
 */
class LearnerDocumentHolder extends DocumentHolder
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

    public function requirementOwnerLabel(): string
    {
        return 'برنامه‌های آموزشی';
    }

    public function requirementOwnerOptions(Tenant $tenant): array
    {
        return Program::withoutTenantScope()->where('tenant_id', $tenant->getKey())->ordered()->pluck('title', 'id')->all();
    }

    public function requirementOwnerId(Model $record): ?int
    {
        return match (true) {
            $record instanceof Preregistration => $record->program_id,
            $record instanceof Student => $record->preregistration()->value('program_id'),
            default => null,
        };
    }

    public function personName(Model $record): string
    {
        return match (true) {
            $record instanceof Preregistration => $record->fullName().' (متقاضی)',
            $record instanceof Student => $record->user?->name ?? '—',
            default => '—',
        };
    }

    public function search(string $search): array
    {
        $search = Identifiers::toLatinDigits(trim($search));

        $applicants = Preregistration::query()->open()
            ->where(fn (Builder $query) => $query
                ->whereLike('first_name', "%{$search}%")
                ->orWhereLike('last_name', "%{$search}%")
                ->orWhereLike('national_code', "%{$search}%"))
            ->limit(10)
            ->get();

        $learners = Student::query()->with('user')
            ->whereHas('user', fn (Builder $user) => $user->whereLike('name', "%{$search}%")->orWhereLike('national_code', "%{$search}%"))
            ->limit(10)
            ->get();

        return $applicants->concat($learners)
            ->mapWithKeys(fn (Model $record): array => [DocumentHolders::recordKey($record) => $this->personName($record)])
            ->all();
    }
}

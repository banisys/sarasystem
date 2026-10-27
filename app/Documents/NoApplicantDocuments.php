<?php

namespace App\Documents;

use Illuminate\Database\Eloquent\Model;

/**
 * Used while the Document module is off: forms ask for no documents.
 */
class NoApplicantDocuments implements ApplicantDocuments
{
    public function fields(string $holder, ?int $requirementOwnerId): array
    {
        return [];
    }

    public function rules(string $holder, ?int $requirementOwnerId, ?Model $applicant = null): array
    {
        return [];
    }

    public function attributes(string $holder, ?int $requirementOwnerId): array
    {
        return [];
    }

    public function store(Model $applicant, array $files, array $expiry): void {}

    public function accepted(Model $applicant, Model $member): void {}
}

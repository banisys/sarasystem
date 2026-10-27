<?php

namespace App\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Documents an applicant uploads in a public form, and what happens to an
 * applicant's documents when they are accepted (ADR 0019). The Document
 * module binds the implementation; without it, forms ask for none.
 * Uploads are sent as `documents.{type id}` (files) and
 * `document_expiry.{type id}` (Jalali date).
 */
interface ApplicantDocuments
{
    public const INPUT = 'documents';

    public const EXPIRY_INPUT = 'document_expiry';

    /**
     * The document types the applicant may upload, for a Vue form.
     *
     * @param  Model|null  $requirementOwner  e.g. the chosen job position
     * @return list<array{id: int, title: string, description: string|null, accept: string, maxFiles: int, maxKilobytes: int, required: bool, hasExpiry: bool}>
     */
    public function fields(string $holder, ?int $requirementOwnerId): array;

    /**
     * Validation rules; a document the open application already has is
     * not required again.
     *
     * @return array<string, mixed>
     */
    public function rules(string $holder, ?int $requirementOwnerId, ?Model $applicant = null): array;

    /**
     * @return array<string, string>
     */
    public function attributes(string $holder, ?int $requirementOwnerId): array;

    /**
     * Store the uploaded documents of an applicant; a new upload of a type
     * replaces the old one and waits for review again.
     *
     * @param  array<int|string, list<UploadedFile>>  $files
     * @param  array<int|string, string|null>  $expiry
     */
    public function store(Model $applicant, array $files, array $expiry): void;

    /**
     * The applicant became a member (hired, admitted): their documents move
     * to the member record and its tenant.
     */
    public function accepted(Model $applicant, Model $member): void;
}

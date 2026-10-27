<?php

namespace Modules\Document\Services;

use App\Documents\ApplicantDocuments;
use App\Rules\JalaliDate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;

/**
 * Documents applicants upload in the public forms, and moving them to the
 * member record on acceptance (ADR 0019).
 */
class PublicFormDocuments implements ApplicantDocuments
{
    public function __construct(private Documents $documents) {}

    public function fields(string $holder, ?int $requirementOwnerId): array
    {
        return $this->types($holder, $requirementOwnerId)
            ->map(fn (DocumentType $type): array => [
                'id' => $type->getKey(),
                'title' => $type->title,
                'description' => $type->description,
                'accept' => $type->file_kind->accept(),
                'maxFiles' => $type->max_files,
                'maxKilobytes' => $type->max_kilobytes,
                'required' => $type->isRequiredFor($holder, $requirementOwnerId),
                'hasExpiry' => $type->has_expiry,
            ])
            ->values()
            ->all();
    }

    public function rules(string $holder, ?int $requirementOwnerId, ?Model $applicant = null): array
    {
        $existing = $applicant === null ? collect() : $this->documents->of($applicant);
        $rules = [self::INPUT => ['nullable', 'array'], self::EXPIRY_INPUT => ['nullable', 'array']];

        foreach ($this->types($holder, $requirementOwnerId) as $type) {
            $key = self::INPUT.'.'.$type->getKey();
            $required = $type->isRequiredFor($holder, $requirementOwnerId) && ! ($existing->get($type->getKey())?->isUploaded() ?? false);

            $rules[$key] = [$required ? 'required' : 'nullable', 'array', 'max:'.$type->max_files];
            $rules[$key.'.*'] = $type->fileRules();

            if ($type->has_expiry) {
                $rules[self::EXPIRY_INPUT.'.'.$type->getKey()] = ['nullable', 'required_with:'.$key, 'string', new JalaliDate];
            }
        }

        return $rules;
    }

    public function attributes(string $holder, ?int $requirementOwnerId): array
    {
        return $this->types($holder, $requirementOwnerId)
            ->flatMap(fn (DocumentType $type): array => [
                self::INPUT.'.'.$type->getKey() => $type->title,
                self::INPUT.'.'.$type->getKey().'.*' => $type->title,
                self::EXPIRY_INPUT.'.'.$type->getKey() => "تاریخ انقضای {$type->title}",
            ])
            ->all();
    }

    public function store(Model $applicant, array $files, array $expiry): void
    {
        $types = DocumentType::definedTypes()->active()->where('applicant_uploads', true)->whereKey(array_keys($files))->get();

        foreach ($types as $type) {
            $uploads = array_values(array_filter((array) ($files[$type->getKey()] ?? [])));

            if ($uploads !== []) {
                $this->documents->upload($applicant, $type, $uploads, JalaliDate::toDate($expiry[$type->getKey()] ?? null)?->toDateString(), null);
            }
        }
    }

    public function accepted(Model $applicant, Model $member): void
    {
        Document::withoutTenantScope()->of($applicant)->update([
            'documentable_type' => $member->getMorphClass(),
            'documentable_id' => $member->getKey(),
            'tenant_id' => $member->getAttribute('tenant_id'),
            'user_id' => $member->getAttribute('user_id'),
        ]);
    }

    /**
     * @return Collection<int, DocumentType>
     */
    private function types(string $holder, ?int $requirementOwnerId): Collection
    {
        return DocumentType::definedTypes()
            ->active()
            ->forHolder($holder)
            ->where('applicant_uploads', true)
            ->with('requirements')
            ->ordered()
            ->get()
            ->filter(fn (DocumentType $type): bool => $type->concernsApplicant($holder, $requirementOwnerId))
            ->values();
    }
}

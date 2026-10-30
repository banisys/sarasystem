<?php

namespace Modules\Document\Services;

use App\CustomFields\FieldMode;
use App\Documents\ApplicantDocuments;
use App\Documents\DocumentHolders;
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
        $modes = DocumentFormFields::modes($holder, $requirementOwnerId);

        return $this->types($modes)
            ->map(fn (DocumentType $type): array => [
                'id' => $type->getKey(),
                'title' => $type->title,
                'description' => $type->description,
                'accept' => $type->file_kind->accept(),
                'maxFiles' => $type->max_files,
                'maxKilobytes' => $type->max_kilobytes,
                'required' => $modes[$type->getKey()] === FieldMode::Required,
                'hasExpiry' => $type->has_expiry,
            ])
            ->values()
            ->all();
    }

    public function rules(string $holder, ?int $requirementOwnerId, ?Model $applicant = null): array
    {
        $existing = $applicant === null ? collect() : $this->documents->of($applicant);
        $modes = DocumentFormFields::modes($holder, $requirementOwnerId);
        $types = $this->types($modes);
        $ids = $types->modelKeys();
        $rules = [
            self::INPUT => $ids === [] ? ['nullable', 'array', 'max:0'] : ['nullable', 'array:'.implode(',', $ids)],
            self::EXPIRY_INPUT => $ids === [] ? ['nullable', 'array', 'max:0'] : ['nullable', 'array:'.implode(',', $ids)],
        ];

        foreach ($types as $type) {
            $key = self::INPUT.'.'.$type->getKey();
            $required = $modes[$type->getKey()] === FieldMode::Required && ! ($existing->get($type->getKey())?->isUploaded() ?? false);

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
        return $this->types(DocumentFormFields::modes($holder, $requirementOwnerId))
            ->flatMap(fn (DocumentType $type): array => [
                self::INPUT.'.'.$type->getKey() => $type->title,
                self::INPUT.'.'.$type->getKey().'.*' => $type->title,
                self::EXPIRY_INPUT.'.'.$type->getKey() => "تاریخ انقضای {$type->title}",
            ])
            ->all();
    }

    public function store(Model $applicant, array $files, array $expiry): void
    {
        $holder = DocumentHolders::forRecord($applicant);
        $types = $holder === null ? new Collection : $this->types(DocumentFormFields::modes($holder->key(), $holder->requirementOwnerId($applicant)))
            ->whereIn('id', array_keys($files));

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
     * @param  array<int, FieldMode>  $modes
     * @return Collection<int, DocumentType>
     */
    private function types(array $modes): Collection
    {
        $ids = array_keys(array_filter($modes, fn (FieldMode $mode): bool => $mode !== FieldMode::Hidden));

        return DocumentType::definedTypes()->active()->whereKey($ids)->ordered()->get();
    }
}

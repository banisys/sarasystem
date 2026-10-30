<?php

namespace Modules\Document\Services;

use App\CustomFields\FieldMode;
use App\Documents\DocumentHolders;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Document\Enums\DocumentStatus;
use Modules\Document\Models\Document;
use Modules\Document\Models\DocumentType;
use RuntimeException;

/**
 * Uploading and checking documents (ADR 0019). Who may do it is checked by
 * the callers (type roles, workflow step, public form).
 */
class Documents
{
    /**
     * Upload (or replace) a record's document of a type. Files are uploads
     * or paths on the local disk (Filament's temporary uploads).
     *
     * @param  list<UploadedFile|string>  $files
     */
    public function upload(Model $record, DocumentType $type, array $files, ?string $expiresAt, ?User $actor): Document
    {
        $holder = DocumentHolders::forRecord($record) ?? throw new RuntimeException('Records of '.$record::class.' have no documents.');

        return DB::transaction(function () use ($record, $type, $files, $expiresAt, $actor, $holder): Document {
            $document = Document::withoutTenantScope()->of($record)->where('document_type_id', $type->getKey())->first() ?? new Document;

            $document->fill([
                'tenant_id' => $record->getAttribute('tenant_id'),
                'document_type_id' => $type->getKey(),
                'holder' => $holder->key(),
                'documentable_type' => $record->getMorphClass(),
                'documentable_id' => $record->getKey(),
                'user_id' => $holder->isApplicant($record) ? null : $record->getAttribute('user_id'),
                'status' => DocumentStatus::Pending,
                'rejection_reason' => null,
                'expires_at' => $type->has_expiry ? $expiresAt : null,
                'uploaded_by_id' => $actor?->getKey(),
                'uploaded_at' => now(),
                'verified_by_id' => null,
                'verified_at' => null,
            ])->save();

            $document->clearMediaCollection(Document::FILES_COLLECTION);

            foreach ($files as $file) {
                $adder = is_string($file) ? $document->addMediaFromDisk($file, 'local') : $document->addMedia($file);
                $adder->toMediaCollection(Document::FILES_COLLECTION);
            }

            return $document->load('media');
        });
    }

    public function approve(Document $document, User $actor): void
    {
        $document->update([
            'status' => DocumentStatus::Approved,
            'rejection_reason' => null,
            'verified_by_id' => $actor->getKey(),
            'verified_at' => now(),
        ]);
    }

    public function reject(Document $document, User $actor, string $reason): void
    {
        $document->update([
            'status' => DocumentStatus::Rejected,
            'rejection_reason' => $reason,
            'verified_by_id' => $actor->getKey(),
            'verified_at' => now(),
        ]);
    }

    /**
     * The record's documents by type id.
     *
     * @return Collection<int, Document>
     */
    public function of(Model $record): Collection
    {
        return Document::withoutTenantScope()->of($record)->with(['media', 'documentType'])->get()->keyBy('document_type_id');
    }

    /**
     * What the record must have: each required type with its document, if any.
     *
     * @return Collection<int, array{type: DocumentType, document: Document|null}>
     */
    public function checklist(Model $record): Collection
    {
        $holder = DocumentHolders::forRecord($record);

        if ($holder === null) {
            return collect();
        }

        $documents = $this->of($record);

        $modes = DocumentFormFields::modes($holder->key(), $holder->requirementOwnerId($record));
        $requiredIds = array_keys(array_filter($modes, fn (FieldMode $mode): bool => $mode === FieldMode::Required));

        return DocumentType::definedTypes()->active()->whereKey($requiredIds)
            ->ordered()
            ->get()
            ->map(fn (DocumentType $type): array => ['type' => $type, 'document' => $documents->get($type->getKey())]);
    }
}

<?php

namespace Modules\Document\Models;

use App\Documents\DocumentHolder;
use App\Documents\DocumentHolders;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;
use Modules\Document\Database\Factories\DocumentFactory;
use Modules\Document\Enums\DocumentStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One document of one person (ADR 0019): the files of a document type,
 * attached to an applicant record (job application, preregistration) and,
 * once the applicant is accepted, to the member record (employee,
 * learner) and its tenant. At most one per record and type; uploading
 * again replaces the files and waits for review again.
 */
#[Fillable([
    'tenant_id', 'document_type_id', 'holder', 'documentable_type', 'documentable_id', 'user_id', 'status', 'rejection_reason',
    'expires_at', 'uploaded_by_id', 'uploaded_at', 'verified_by_id', 'verified_at',
])]
class Document extends Model implements HasMedia
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToTenant, HasFactory, InteractsWithMedia;

    public const FILES_COLLECTION = 'files';

    /** Days before expiry when a document counts as expiring. */
    public const EXPIRING_WITHIN_DAYS = 30;

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }

    /**
     * Only the private disk: documents are personal data, downloaded
     * through signed links (DocumentFileController).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::FILES_COLLECTION)->useDisk('local');
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeOf(Builder $query, Model $record): void
    {
        $query->where('documentable_type', $record->getMorphClass())->where('documentable_id', $record->getKey());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && ! $this->expires_at->isToday();
    }

    public function isExpiring(): bool
    {
        return $this->expires_at !== null && ! $this->isExpired() && $this->expires_at->lessThanOrEqualTo(now()->addDays(self::EXPIRING_WITHIN_DAYS));
    }

    public function hasFiles(): bool
    {
        return $this->getMedia(self::FILES_COLLECTION)->isNotEmpty();
    }

    /**
     * Uploaded and usable: has files and is not expired or rejected.
     */
    public function isUploaded(): bool
    {
        return $this->status !== DocumentStatus::Rejected && ! $this->isExpired() && $this->hasFiles();
    }

    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::Approved && ! $this->isExpired();
    }

    public function holderDescriptor(): ?DocumentHolder
    {
        return DocumentHolders::find($this->holder);
    }

    public function personName(): string
    {
        $record = $this->documentable;

        return $record === null ? '—' : ($this->holderDescriptor()?->personName($record) ?? '—');
    }

    /**
     * Signed download links of the files, valid for a while.
     *
     * @return list<array{name: string, url: string}>
     */
    public function fileLinks(): array
    {
        return $this->getMedia(self::FILES_COLLECTION)
            ->map(fn (Media $media): array => [
                'name' => $media->file_name,
                'url' => URL::temporarySignedRoute('documents.file', now()->addMinutes(30), ['media' => $media->getKey()]),
            ])
            ->values()
            ->all();
    }

    /**
     * Types are the primary tenant's.
     *
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Unscoped: an applicant record is the primary tenant's while its case
     * may be open in another tenant's panel.
     *
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Null when the applicant uploaded it in a public form.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'expires_at' => 'date',
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}

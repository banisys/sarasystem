<?php

namespace Modules\Document\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Document\Database\Factories\DocumentTypeFactory;
use Modules\Document\Enums\FileKind;
use Modules\Document\Enums\RequiredFor;

/**
 * A kind of document people have, e.g. «کارت ملی» (ADR 0019). Defined
 * once, in the primary tenant, for one or more holders (employees,
 * learners). Holders of its upload roles may upload it and holders of its
 * verify roles may approve or reject it, in the document's tenant; a
 * workflow step may also ask its assignee to do either.
 *
 * @property list<string> $holders
 * @property list<int> $upload_role_ids
 * @property list<int> $verify_role_ids
 */
#[Fillable([
    'tenant_id', 'title', 'description', 'holders', 'file_kind', 'max_files', 'max_kilobytes', 'has_expiry', 'applicant_uploads',
    'upload_role_ids', 'verify_role_ids', 'required_for', 'is_active', 'sort',
])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): DocumentTypeFactory
    {
        return DocumentTypeFactory::new();
    }

    /**
     * Types of the primary tenant, from anywhere (documents live in every
     * tenant, their types in the primary one).
     *
     * @return Builder<DocumentType>
     */
    public static function definedTypes(): Builder
    {
        return static::withoutTenantScope()->where('tenant_id', Tenant::primary()?->getKey());
    }

    /**
     * Active types of the holder that are required for the requirement
     * owner (or for everyone).
     *
     * @return Builder<DocumentType>
     */
    public static function requiredFor(string $holder, ?int $ownerId): Builder
    {
        return static::definedTypes()->active()->forHolder($holder)->where(fn (Builder $query) => $query
            ->where('required_for', RequiredFor::Everyone)
            ->orWhere(fn (Builder $query) => $query
                ->where('required_for', RequiredFor::Selected)
                ->whereHas('requirements', fn (Builder $requirement) => $requirement->where('holder', $holder)->where('owner_id', $ownerId))));
    }

    /**
     * @param  Builder<DocumentType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<DocumentType>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('title');
    }

    /**
     * @param  Builder<DocumentType>  $query
     */
    public function scopeForHolder(Builder $query, string $holder): void
    {
        $query->whereJsonContains('holders', $holder);
    }

    public function isRequiredFor(string $holder, ?int $ownerId): bool
    {
        return match ($this->required_for) {
            RequiredFor::Nobody => false,
            RequiredFor::Everyone => true,
            RequiredFor::Selected => $ownerId !== null && $this->requirements->contains(fn (DocumentTypeRequirement $requirement): bool => $requirement->holder === $holder && $requirement->owner_id === $ownerId),
        };
    }

    /**
     * Whether an applicant may see it in their form: required for their
     * choice, or optional for everyone.
     */
    public function concernsApplicant(string $holder, ?int $ownerId): bool
    {
        return $this->applicant_uploads && ($this->required_for === RequiredFor::Nobody || $this->isRequiredFor($holder, $ownerId));
    }

    public function allowsUpload(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() || array_intersect($user->roleIdsIn($tenant), $this->upload_role_ids) !== [];
    }

    public function allowsVerify(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() || array_intersect($user->roleIdsIn($tenant), $this->verify_role_ids) !== [];
    }

    /**
     * Rules for one uploaded file.
     *
     * @return list<string>
     */
    public function fileRules(): array
    {
        return ['file', 'mimes:'.implode(',', $this->file_kind->extensions()), 'max:'.$this->max_kilobytes];
    }

    /**
     * @return HasMany<DocumentTypeRequirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(DocumentTypeRequirement::class);
    }

    /**
     * Documents of every tenant use the type.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->withoutGlobalScope(TenantScope::class);
    }

    protected function casts(): array
    {
        return [
            'holders' => 'array',
            'file_kind' => FileKind::class,
            'has_expiry' => 'boolean',
            'applicant_uploads' => 'boolean',
            'upload_role_ids' => 'array',
            'verify_role_ids' => 'array',
            'required_for' => RequiredFor::class,
            'is_active' => 'boolean',
        ];
    }
}

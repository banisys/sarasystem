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

/**
 * A kind of document people have, e.g. «کارت ملی» (ADR 0019). Defined
 * once, in the primary tenant, independently of public forms and holder
 * kinds. Holders of its upload roles may upload it and holders of its
 * verify roles may approve or reject it, in the document's tenant; a
 * workflow step may also ask its assignee to do either.
 *
 * @property list<int> $upload_role_ids
 * @property list<int> $verify_role_ids
 */
#[Fillable([
    'tenant_id', 'title', 'description', 'file_kind', 'max_files', 'max_kilobytes', 'has_expiry',
    'upload_role_ids', 'verify_role_ids', 'is_active', 'sort',
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
            'file_kind' => FileKind::class,
            'has_expiry' => 'boolean',
            'upload_role_ids' => 'array',
            'verify_role_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

<?php

namespace Modules\Document\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
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
 * kinds.
 */
#[Fillable([
    'tenant_id', 'title', 'description', 'file_kind', 'max_files', 'max_kilobytes', 'has_expiry',
    'is_active', 'sort',
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
            'is_active' => 'boolean',
        ];
    }
}

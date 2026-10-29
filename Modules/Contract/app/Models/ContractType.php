<?php

namespace Modules\Contract\Models;

use App\Contract\ContractParties;
use App\Contract\ContractParty;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contract\Database\Factories\ContractTypeFactory;
use Modules\Contract\Enums\SignatureMethod;
use Modules\Contract\Forms\ContractForm;

/**
 * A kind of contract, e.g. «قرارداد کارمند تمام‌وقت» (ADR 0020). Defined
 * once, in the primary tenant, for one or more parties (employees,
 * learners): its text with merge tags, how it is signed, and its fields
 * (a customizable form per type, ADR 0017). An amendment type is used
 * for amendments of signed contracts only (ADR 0021).
 *
 * @property list<string> $parties
 */
#[Fillable(['tenant_id', 'title', 'description', 'parties', 'is_amendment', 'body', 'signature_method', 'number_prefix', 'is_active', 'sort'])]
class ContractType extends Model
{
    /** @use HasFactory<ContractTypeFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ContractTypeFactory
    {
        return ContractTypeFactory::new();
    }

    /**
     * Types of the primary tenant, from anywhere (contracts live in every
     * tenant, their types in the primary one).
     *
     * @return Builder<ContractType>
     */
    public static function definedTypes(): Builder
    {
        return static::withoutTenantScope()->where('tenant_id', Tenant::primary()?->getKey());
    }

    /**
     * @param  Builder<ContractType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<ContractType>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('title');
    }

    /**
     * Types for contracts of their own and renewals (true: amendment types).
     *
     * @param  Builder<ContractType>  $query
     */
    public function scopeAmendments(Builder $query, bool $amendments = true): void
    {
        $query->where('is_amendment', $amendments);
    }

    /**
     * @param  Builder<ContractType>  $query
     */
    public function scopeForParty(Builder $query, string $party): void
    {
        $query->whereJsonContains('parties', $party);
    }

    /**
     * Merge tags of the type's text: key => label.
     *
     * @return array<string, string>
     */
    public function mergeTags(): array
    {
        return self::tagsFor($this->parties ?? [], $this->exists ? $this : null);
    }

    /**
     * Merge tags of a text for these parties: the contract's own values,
     * the contract it amends or renews (ADR 0021), the person tags, every party's tags, then the added fields of the
     * type's form (once the type exists).
     *
     * @param  list<string>  $parties
     * @return array<string, string>
     */
    public static function tagsFor(array $parties, ?self $type): array
    {
        $tags = [
            'contract_title' => 'عنوان قرارداد',
            'contract_number' => 'شماره قرارداد',
            'issued_on' => 'تاریخ صدور',
            'tenant_name' => 'نام '.config('sarasystem.tenant_label'),
            'starts_on' => 'تاریخ شروع',
            'ends_on' => 'تاریخ پایان',
            'amount' => 'مبلغ (ریال)',
            'amount_words' => 'مبلغ به حروف',
            'parent_number' => 'شماره‌ی قرارداد اصلی / قبلی',
            'parent_title' => 'عنوان قرارداد اصلی / قبلی',
            'parent_starts_on' => 'تاریخ شروع قرارداد اصلی / قبلی',
            'parent_ends_on' => 'تاریخ پایان قرارداد اصلی / قبلی',
            ...ContractParty::personTags(),
        ];

        foreach ($parties as $key) {
            $tags = [...$tags, ...(ContractParties::find($key)?->tags() ?? [])];
        }

        foreach ($type === null ? [] : ContractForm::for($type)->fields as $field) {
            $tags[$field->key] = $field->label;
        }

        return $tags;
    }

    /**
     * Contracts of every tenant use the type.
     *
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->withoutGlobalScope(TenantScope::class);
    }

    protected function casts(): array
    {
        return [
            'parties' => 'array',
            'is_amendment' => 'boolean',
            'signature_method' => SignatureMethod::class,
            'is_active' => 'boolean',
        ];
    }
}

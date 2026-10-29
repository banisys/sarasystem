<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Database\Factories\TermFactory;

/**
 * A teaching period of the organization, e.g. «سال تحصیلی ۱۴۰۵-۱۴۰۶» or
 * «ترم پاییز ۱۴۰۵» (ADR 0022). Defined once, in the primary tenant;
 * classes of every tenant run in one. A school's academic year is a term.
 */
#[Fillable(['tenant_id', 'title', 'starts_on', 'ends_on', 'is_active'])]
class Term extends Model
{
    /** @use HasFactory<TermFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'class_terms';

    protected static function newFactory(): TermFactory
    {
        return TermFactory::new();
    }

    /**
     * Terms of the primary tenant, from anywhere.
     *
     * @return Builder<Term>
     */
    public static function definedTerms(): Builder
    {
        return static::withoutTenantScope()->where('tenant_id', Tenant::primary()?->getKey());
    }

    /**
     * Active terms, newest first, for pickers.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::definedTerms()->where('is_active', true)->orderByDesc('starts_on')->pluck('title', 'id')->all();
    }

    /**
     * Classes of every tenant run in it.
     *
     * @return HasMany<Classroom, $this>
     */
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class)->withoutGlobalScope(TenantScope::class);
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}

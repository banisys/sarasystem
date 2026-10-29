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
use Modules\Class\Database\Factories\SubjectFactory;

/**
 * What a class schedule teaches, e.g. «ریاضی», «Speaking», «ایمنی کار»
 * (ADR 0023). Optional: a class that is itself one course needs none.
 * Defined once, in the primary tenant. A School module adds grades and
 * coefficients on its side.
 */
#[Fillable(['tenant_id', 'title', 'is_active', 'sort'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'class_subjects';

    protected static function newFactory(): SubjectFactory
    {
        return SubjectFactory::new();
    }

    /**
     * Subjects of the primary tenant, from anywhere.
     *
     * @return Builder<Subject>
     */
    public static function definedSubjects(): Builder
    {
        return static::withoutTenantScope()->where('tenant_id', Tenant::primary()?->getKey())->orderBy('sort')->orderBy('title');
    }

    /**
     * Active subjects, plus the given one even if inactive (a record's own).
     *
     * @return array<int, string>
     */
    public static function options(?int $including = null): array
    {
        return static::definedSubjects()
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($including, fn (Builder $query) => $query->orWhereKey($including)))
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * Schedules of every tenant use it.
     *
     * @return HasMany<ClassSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class)->withoutGlobalScope(TenantScope::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

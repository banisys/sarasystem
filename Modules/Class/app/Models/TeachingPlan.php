<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Database\Factories\TeachingPlanFactory;

/**
 * A teaching plan («طرح درس», ADR 0025): the topics a course goes through,
 * in order, each taking some sessions, e.g. «ریاضی هفتم» or «English
 * Elementary — Book 1». Defined once, in the primary tenant; a schedule
 * follows one, and its sessions are given the topics a class has not
 * covered yet (PlanForecast).
 */
#[Fillable(['tenant_id', 'subject_id', 'title', 'description', 'is_active'])]
class TeachingPlan extends Model
{
    /** @use HasFactory<TeachingPlanFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'class_teaching_plans';

    protected static function newFactory(): TeachingPlanFactory
    {
        return TeachingPlanFactory::new();
    }

    /**
     * Plans of the primary tenant, from anywhere.
     *
     * @return Builder<TeachingPlan>
     */
    public static function definedPlans(): Builder
    {
        return static::withoutTenantScope()->where('tenant_id', Tenant::primary()?->getKey())->orderBy('title');
    }

    /**
     * Active plans (the subject's and general ones first), plus the given one.
     *
     * @return array<int, string>
     */
    public static function options(?int $subjectId = null, ?int $including = null): array
    {
        return static::definedPlans()
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($including, fn (Builder $query) => $query->orWhereKey($including)))
            ->when($subjectId, fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('subject_id', $subjectId)->orWhereNull('subject_id')))
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return HasMany<PlanTopic, $this>
     */
    public function topics(): HasMany
    {
        return $this->hasMany(PlanTopic::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Subjects are the primary tenant's.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Schedules of every tenant follow it.
     *
     * @return HasMany<ClassSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class)->withoutGlobalScope(TenantScope::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

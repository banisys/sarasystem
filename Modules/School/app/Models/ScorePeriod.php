<?php

namespace Modules\School\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Models\Term;

/**
 * A score period of an academic year («دوره‌ی نمره‌دهی»), e.g. «مهر»,
 * «نوبت اول» (ADR 0032): teachers give each learner a score per subject
 * in it, until its entry closes; once published, its report cards are
 * shown to learners and guardians. Defined in the primary tenant.
 */
#[Fillable(['tenant_id', 'term_id', 'title', 'starts_on', 'ends_on', 'entry_closes_on', 'is_published', 'sort'])]
class ScorePeriod extends Model
{
    use BelongsToTenant;

    protected $table = 'school_score_periods';

    /**
     * @return Builder<ScorePeriod>
     */
    public static function definedPeriods(): Builder
    {
        return static::withoutTenantScope()->where('tenant_id', Tenant::primary()?->getKey())->orderBy('sort')->orderBy('starts_on');
    }

    public function isOpenForTeachers(): bool
    {
        return ! $this->is_published && ($this->entry_closes_on === null || ! $this->entry_closes_on->copy()->endOfDay()->isPast());
    }

    /**
     * Terms are the primary tenant's.
     *
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class)->withoutGlobalScopes();
    }

    /**
     * Scores of every tenant.
     *
     * @return HasMany<Score, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'period_id')->withoutGlobalScopes();
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'entry_closes_on' => 'date',
            'is_published' => 'boolean',
        ];
    }
}

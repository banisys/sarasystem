<?php

namespace Modules\School\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A grade («پایه»), e.g. «هفتم», in a stage (ADR 0031). Grades of upper
 * secondary have majors. Its curriculum says which subjects it has and
 * their coefficients. Grades in stage order, then their own, are the order
 * learners move up in. Defined in the primary tenant.
 */
#[Fillable(['tenant_id', 'stage_id', 'title', 'has_majors', 'sort'])]
class Grade extends Model
{
    use BelongsToTenant;

    protected $table = 'school_grades';

    /**
     * Grades of the primary tenant, in the order learners move up in.
     *
     * @return Builder<Grade>
     */
    public static function definedGrades(): Builder
    {
        return static::withoutTenantScope()
            ->where('school_grades.tenant_id', Tenant::primary()?->getKey())
            ->join('school_stages', 'school_stages.id', '=', 'school_grades.stage_id')
            ->orderBy('school_stages.sort')
            ->orderBy('school_stages.id')
            ->orderBy('school_grades.sort')
            ->orderBy('school_grades.id')
            ->select('school_grades.*');
    }

    /**
     * «هفتم (متوسطه اول)» by id, in order.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::definedGrades()->with('stage')->get()
            ->mapWithKeys(fn (Grade $grade): array => [$grade->getKey() => $grade->label()])
            ->all();
    }

    public function label(): string
    {
        return $this->title.($this->stage ? " ({$this->stage->title})" : '');
    }

    /**
     * The grade after this one, or null for the last.
     */
    public function next(): ?self
    {
        /** @var Collection<int, Grade> $grades */
        $grades = static::definedGrades()->get()->values();
        $index = $grades->search(fn (Grade $grade): bool => $grade->is($this));

        return $index === false ? null : $grades->get($index + 1);
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<Curriculum, $this>
     */
    public function curricula(): HasMany
    {
        return $this->hasMany(Curriculum::class)->orderBy('sort')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'has_majors' => 'boolean',
        ];
    }
}

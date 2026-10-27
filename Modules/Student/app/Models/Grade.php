<?php

namespace Modules\Student\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasFormCustomizations;
use App\Models\Scopes\TenantScope;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Student\Database\Factories\GradeFactory;

/**
 * What people preregister for (ADR 0018), in two levels: a stage (مقطع,
 * no parent) with its grades (پایه). A stage without grades is chosen
 * itself, so a language institute can list flat levels. The stage holds
 * the admission workflow (ADR 0016) and its own setup of the form (ADR 0017).
 */
#[Fillable(['tenant_id', 'parent_id', 'workflow_id', 'title', 'description', 'is_active', 'sort'])]
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use BelongsToTenant, HasFactory, HasFormCustomizations;

    protected $table = 'student_grades';

    protected static function newFactory(): GradeFactory
    {
        return GradeFactory::new();
    }

    /**
     * Select options of a tenant's grades: grades grouped under their
     * stage, a stage without grades as an option of its own.
     *
     * @return array<int|string, string|array<int, string>>
     */
    public static function selectOptions(int $tenantId): array
    {
        $stages = static::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->stages()
            ->ordered()
            ->with(['children' => fn ($query) => $query->ordered()])
            ->get();

        $options = [];

        foreach ($stages as $stage) {
            if ($stage->children->isEmpty()) {
                $options[$stage->getKey()] = $stage->title;
            } else {
                $options[$stage->title] = $stage->children->pluck('title', 'id')->all();
            }
        }

        return $options;
    }

    /**
     * @param  Builder<Grade>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Grade>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /**
     * @param  Builder<Grade>  $query
     */
    public function scopeStages(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function isStage(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * The stage itself, or the grade's stage.
     */
    public function stage(): self
    {
        return $this->isStage() ? $this : $this->parent;
    }

    /**
     * «متوسطه اول - هفتم», or the stage's title for a stage.
     */
    public function fullTitle(): string
    {
        return $this->isStage() ? $this->title : "{$this->parent->title} - {$this->title}";
    }

    /**
     * Unscoped: a preregistration's grade is also read in other tenants'
     * panels (the cartable, the students list).
     *
     * @return BelongsTo<Grade, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Unscoped like parent(); a stage's grades are always in its tenant.
     *
     * @return HasMany<Grade, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return HasMany<Preregistration, $this>
     */
    public function preregistrations(): HasMany
    {
        return $this->hasMany(Preregistration::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

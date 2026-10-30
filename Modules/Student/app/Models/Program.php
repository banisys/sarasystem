<?php

namespace Modules\Student\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasFormCustomizations;
use App\Models\Tenant;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Student\Database\Factories\ProgramFactory;

/**
 * What people preregister for (ADR 0018): whatever the organization offers,
 * e.g. «پایه هفتم» in a school, «زبان انگلیسی - مقدماتی» in an institute,
 * «دوره‌ی بدو خدمت» in a company. Its workflow, if any, runs on each new
 * preregistration (ADR 0016); it may have its own setup of the form (ADR 0017).
 */
#[Fillable(['tenant_id', 'workflow_id', 'title', 'description', 'all_tenants', 'is_active', 'sort'])]
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use BelongsToTenant, HasFactory, HasFormCustomizations;

    protected $table = 'student_programs';

    protected $attributes = ['all_tenants' => true];

    protected static function newFactory(): ProgramFactory
    {
        return ProgramFactory::new();
    }

    /**
     * @param  Builder<Program>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Program>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('title');
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /** @return BelongsToMany<Tenant, $this> */
    public function availableInTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'student_program_tenant');
    }

    /**
     * Availability is separate from ownership: programs are managed centrally.
     *
     * @return Builder<Tenant>
     */
    public function availableTenants(): Builder
    {
        return Tenant::query()->active()->orderBy('name')
            ->when(! $this->all_tenants, fn (Builder $query) => $query->whereKey($this->availableInTenants->modelKeys()));
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
            'all_tenants' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

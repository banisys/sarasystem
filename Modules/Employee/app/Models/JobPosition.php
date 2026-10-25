<?php

namespace Modules\Employee\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Employee\Database\Factories\JobPositionFactory;

/**
 * A job people can apply for in the «همکاری با ما» form (ADR 0014).
 */
#[Fillable(['tenant_id', 'title', 'description', 'is_active', 'sort'])]
class JobPosition extends Model
{
    /** @use HasFactory<JobPositionFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'employee_job_positions';

    protected static function newFactory(): JobPositionFactory
    {
        return JobPositionFactory::new();
    }

    /**
     * @param  Builder<JobPosition>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<JobPosition>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('title');
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

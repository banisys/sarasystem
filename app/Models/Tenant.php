<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

/**
 * One organizational unit of the installation: a school, branch or department.
 *
 * Membership is not stored separately: a user belongs to a tenant when they
 * hold at least one role in it (Spatie permission teams, team = tenant).
 */
#[Fillable(['name', 'slug', 'is_active'])]
class Tenant extends BaseTenant
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Users holding any role in this tenant.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, config('permission.table_names.model_has_roles'), 'tenant_id', config('permission.column_names.model_morph_key'))
            ->wherePivot('model_type', (new User)->getMorphClass())
            ->distinct();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

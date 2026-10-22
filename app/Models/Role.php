<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as BaseRole;

/**
 * A role definition. tenant_id NULL means a global role (e.g. "teacher")
 * that can be assigned in any tenant; a set tenant_id limits the role to
 * that tenant. Assignments are always per tenant (model_has_roles.tenant_id).
 */
class Role extends BaseRole
{
    /**
     * The label shown in the UI: the Persian `label`, or the technical name
     * made readable when no label is set.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->label ?: Str::headline($this->name));
    }

    /**
     * The tenant a tenant-specific role belongs to. Named "team" because
     * Shield's RoleResource reads `team.name`.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Roles that can be assigned in the tenant: global ones and its own.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAssignableIn(Builder $query, Tenant $tenant): void
    {
        $column = $query->qualifyColumn('tenant_id');

        $query->where(fn (Builder $query) => $query
            ->whereNull($column)
            ->orWhere($column, $tenant->getKey()));
    }
}

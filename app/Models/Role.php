<?php

namespace App\Models;

use BezhanSalleh\FilamentShield\Support\Utils;
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
     * Global role whose holders are installation-wide super admins, in
     * whichever tenant they hold it (ADR 0007).
     */
    public const string SUPER_ADMIN = 'super_admin';

    /**
     * Roles the code relies on by name. They can't be deleted, and their
     * name, guard and tenant can't change (also enforced by booted()).
     */
    public function isSystemRole(): bool
    {
        return static::isSystemRoleName($this->getOriginal('name'), $this->getOriginal('tenant_id'));
    }

    protected static function booted(): void
    {
        static::deleting(fn (Role $role): bool => ! $role->isSystemRole());

        static::updating(fn (Role $role): bool => ! ($role->isSystemRole() && $role->isDirty(['name', 'guard_name', 'tenant_id'])));
    }

    private static function isSystemRoleName(?string $name, mixed $tenantId): bool
    {
        return $tenantId === null && in_array($name, [self::SUPER_ADMIN, Utils::getPanelUserRoleName()], true);
    }

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

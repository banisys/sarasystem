<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Identifiers;
use BezhanSalleh\FilamentShield\Support\Utils;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * Users are global (not tenant-scoped). Their place in each tenant comes from
 * the roles they hold there; see docs/decisions/0002-tenancy-model.md.
 */
#[Fillable(['name', 'email', 'mobile', 'national_code', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Tenants in which the user holds any role (staff, student, parent, ...).
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, config('permission.table_names.model_has_roles'), config('permission.column_names.model_morph_key'), 'tenant_id')
            ->wherePivot('model_type', $this->getMorphClass())
            ->distinct();
    }

    /**
     * Tenants in which the user is staff: holds Shield's panel_user role there.
     * panel_user is never given automatically (unlike Shield's HasPanelShield
     * trait), so students and parents never reach the admin panel.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function staffTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivotIn(
            'role_id',
            Role::query()->where('name', Utils::getPanelUserRoleName())->pluck('id'),
        );
    }

    /**
     * Users matching a login identifier: email, mobile or national code
     * (ADR 0004). More than one match means a shared mobile.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWhereLoginIdentifier(Builder $query, string $identifier): void
    {
        $identifier = trim(Identifiers::toLatinDigits($identifier));

        if (str_contains($identifier, '@')) {
            $query->where('email', Str::lower($identifier));

            return;
        }

        $mobile = Identifiers::normalizeMobile($identifier);

        $query->where(fn (Builder $query) => $query
            ->where('national_code', $identifier)
            ->when($mobile !== null, fn (Builder $query) => $query->orWhere('mobile', $mobile)));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? Str::lower(trim($value)) : null);
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function mobile(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? (Identifiers::normalizeMobile($value) ?? $value) : null);
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function nationalCode(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? Identifiers::toLatinDigits(trim($value)) : null);
    }

    /**
     * Users holding any role in the tenant.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInTenant(Builder $query, Tenant $tenant): void
    {
        $query->whereExists(fn (QueryBuilder $roles) => $roles
            ->selectRaw('1')
            ->from(config('permission.table_names.model_has_roles'))
            ->whereColumn(config('permission.column_names.model_morph_key'), $query->qualifyColumn('id'))
            ->where('model_type', $this->getMorphClass())
            ->where('tenant_id', $tenant->getKey()));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_super_admin || $this->staffTenants()->exists();
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->is_super_admin) {
            return Tenant::query()->active()->orderBy('name')->get();
        }

        return $this->staffTenants()->active()->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Tenant || ! $tenant->is_active) {
            return false;
        }

        return $this->is_super_admin || $this->staffTenants()->whereKey($tenant->getKey())->exists();
    }

    /**
     * Whether the user may work in the tenant outside the admin panel
     * (user panel / public site), in any role.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        if (! $tenant->is_active) {
            return false;
        }

        return $this->is_super_admin || $this->tenants()->whereKey($tenant->getKey())->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }
}

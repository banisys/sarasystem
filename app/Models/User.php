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
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
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
     * The users this user is a parent or guardian of (ADR 0027).
     *
     * @return BelongsToMany<User, $this>
     */
    public function wards(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardianships', 'guardian_id', 'ward_id')->withPivot(['relationship', 'receives_sms'])->withTimestamps();
    }

    /**
     * This user's parents or guardians (ADR 0027).
     *
     * @return BelongsToMany<User, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardianships', 'ward_id', 'guardian_id')->withPivot(['relationship', 'receives_sms'])->withTimestamps();
    }

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
     * Tenants in which the user holds the super_admin role. Holding it in any
     * tenant makes the user super admin of the whole installation (ADR 0007).
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function superAdminTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivotIn(
            'role_id',
            Role::query()->whereNull('tenant_id')->where('name', Role::SUPER_ADMIN)->pluck('id'),
        );
    }

    /**
     * Installation-wide super admin: passes every permission check
     * (Gate::before), sees every tenant and manages tenants and role
     * definitions. Read from the loaded superAdminTenants relation, so it
     * costs one query per user instance.
     */
    public function isSuperAdmin(): bool
    {
        return $this->superAdminTenants->isNotEmpty();
    }

    /**
     * Give the user the super_admin role, held in the given tenant.
     */
    public function makeSuperAdmin(Tenant $tenant): void
    {
        $tenant->execute(fn () => $this->unsetRelation('roles')->assignRole(Role::SUPER_ADMIN));

        $this->unsetRelation('roles')->unsetRelation('superAdminTenants');
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

    /**
     * Ids of the roles the user holds in the tenant.
     *
     * @return list<int>
     */
    public function roleIdsIn(Tenant $tenant): array
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->where(config('permission.column_names.model_morph_key'), $this->getKey())
            ->where('model_type', $this->getMorphClass())
            ->where('tenant_id', $tenant->getKey())
            ->pluck('role_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * The user's tenants (by name), each with the roles held there.
     *
     * @return SupportCollection<int, array{tenant: Tenant, roles: Collection<int, Role>}>
     */
    public function memberships(): SupportCollection
    {
        $rows = DB::table(config('permission.table_names.model_has_roles'))
            ->where(config('permission.column_names.model_morph_key'), $this->getKey())
            ->where('model_type', $this->getMorphClass())
            ->get(['tenant_id', 'role_id']);

        $roles = Role::query()->whereKey($rows->pluck('role_id')->unique())->get()->keyBy('id');

        return Tenant::query()->whereKey($rows->pluck('tenant_id')->unique())->orderBy('name')->get()
            ->map(fn (Tenant $tenant): array => [
                'tenant' => $tenant,
                'roles' => $roles->only($rows->where('tenant_id', $tenant->getKey())->pluck('role_id')->all())->values(),
            ]);
    }

    /**
     * Adds `tenants_count`: the number of tenants in which the user holds
     * any role.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithTenantsCount(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->qualifyColumn('*'));
        }

        $query->selectSub(fn (QueryBuilder $roles) => $roles
            ->selectRaw('count(distinct tenant_id)')
            ->from(config('permission.table_names.model_has_roles'))
            ->whereColumn(config('permission.column_names.model_morph_key'), $query->qualifyColumn('id'))
            ->where('model_type', $this->getMorphClass()), 'tenants_count');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isSuperAdmin() || $this->staffTenants()->exists();
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->isSuperAdmin()) {
            return Tenant::query()->active()->orderBy('name')->get();
        }

        return $this->staffTenants()->active()->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Tenant || ! $tenant->is_active) {
            return false;
        }

        return $this->isSuperAdmin() || $this->staffTenants()->whereKey($tenant->getKey())->exists();
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

        return $this->isSuperAdmin() || $this->tenants()->whereKey($tenant->getKey())->exists();
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
        ];
    }
}

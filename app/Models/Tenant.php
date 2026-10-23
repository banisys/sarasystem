<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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
            'is_primary' => 'boolean',
        ];
    }

    /**
     * The tenant whose home page is the site root (ADR 0009), if one is set.
     */
    public static function primary(): ?self
    {
        return static::query()->active()->where('is_primary', true)->first();
    }

    /**
     * Make this the only primary tenant.
     */
    public function makePrimary(): void
    {
        DB::transaction(function (): void {
            static::query()->whereKeyNot($this->getKey())->where('is_primary', true)->update(['is_primary' => false]);
            $this->forceFill(['is_primary' => true])->save();
        });
    }

    /**
     * Public home page: the site root for the primary tenant, /{slug} for the others.
     */
    public function homeUrl(): string
    {
        return $this->is_primary ? route('home') : route('tenants.home', $this);
    }

    /**
     * Slugs a tenant may not take, because /{slug} would clash with another
     * route or a public directory (cpadmin, login, build, ...).
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        $routeSegments = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => Str::before(ltrim($route->uri(), '/'), '/'))
            ->reject(fn (string $segment): bool => $segment === '' || str_starts_with($segment, '{'));

        $publicEntries = collect(File::glob(public_path('*')))->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME));

        return $routeSegments->merge($publicEntries)->map(fn (string $name): string => Str::lower($name))->unique()->values()->all();
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

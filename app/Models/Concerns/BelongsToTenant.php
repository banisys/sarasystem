<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Exceptions\NoCurrentTenant;

/**
 * For every model whose rows belong to one tenant (table has `tenant_id`).
 *
 * Queries are scoped to the current tenant and new rows get its id. The
 * `tenant` relationship is also what Filament panel tenancy uses as the
 * ownership relationship.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenant = Tenant::current() ?? throw NoCurrentTenant::make();

            $model->setAttribute('tenant_id', $tenant->getKey());
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Query across all tenants. Only for code that is allowed to see every
     * tenant (e.g. head-office reports); callers must check authorization.
     *
     * @return Builder<static>
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}

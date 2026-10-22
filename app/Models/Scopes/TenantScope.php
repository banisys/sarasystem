<?php

namespace App\Models\Scopes;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits tenant-owned models to the current tenant.
 *
 * Fails closed: with no current tenant the query matches nothing, so a
 * missing tenant can never leak another tenant's rows. Cross-tenant code
 * (head-office reports, console jobs) must opt out explicitly with
 * Model::withoutTenantScope() or run inside $tenant->execute(...).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenant->getKey());
    }
}

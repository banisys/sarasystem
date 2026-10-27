<?php

namespace App\Filament\Concerns;

use App\Models\Tenant;
use Filament\Facades\Filament;

/**
 * For resources of a public form that lives on the primary tenant's site
 * only, e.g. «همکاری با ما» (ADR 0014) or student preregistration
 * (ADR 0018): their records are managed in that tenant's panel only.
 */
trait OnlyInPrimaryTenant
{
    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant && $tenant->is_primary && parent::canAccess();
    }
}

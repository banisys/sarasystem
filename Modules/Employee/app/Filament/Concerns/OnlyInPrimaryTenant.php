<?php

namespace Modules\Employee\Filament\Concerns;

use App\Models\Tenant;
use Filament\Facades\Filament;

/**
 * The «همکاری با ما» form is the primary tenant's only (ADR 0014), so its
 * positions and applications are managed in that tenant's panel only.
 */
trait OnlyInPrimaryTenant
{
    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant && $tenant->is_primary && parent::canAccess();
    }
}

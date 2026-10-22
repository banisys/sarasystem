<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament tenant middleware (registered as persistent, so it also runs on
 * Livewire updates): makes the panel's tenant the spatie-multitenancy current
 * tenant, which also switches the permission team (SwitchPermissionsTeamTask).
 * This keeps Filament, the BelongsToTenant scope and Shield on one tenant.
 */
class MakeFilamentTenantCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Tenant && ! $tenant->isCurrent()) {
            $tenant->makeCurrent();

            // Roles/permissions loaded before the switch belong to another team.
            $request->user()?->unsetRelation('roles')->unsetRelation('permissions');
        }

        return $next($request);
    }
}

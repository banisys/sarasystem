<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For user-panel routes that work inside one tenant: without a current
 * tenant (ResolveCurrentTenant found none), send the user to the picker.
 */
class EnsureTenantSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Tenant::checkCurrent()) {
            return redirect()->guest(route('tenants.select'));
        }

        return $next($request);
    }
}

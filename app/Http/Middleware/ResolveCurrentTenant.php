<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web (Inertia) side: the current tenant is the one the user picked after
 * login, kept in the session. A user who belongs to exactly one tenant gets
 * it automatically. A stored tenant the user no longer belongs to is dropped.
 *
 * spatie-multitenancy's own tenant finder runs before the session exists, so
 * tenant resolution happens here instead.
 */
class ResolveCurrentTenant
{
    public const SESSION_KEY = 'tenant_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $tenant = $this->resolve($request, $user);

            if ($tenant !== null && ! $tenant->isCurrent()) {
                $tenant->makeCurrent();
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }
        }

        return $next($request);
    }

    private function resolve(Request $request, User $user): ?Tenant
    {
        $session = $request->session();
        $tenant = Tenant::find($session->get(self::SESSION_KEY));

        if ($tenant !== null && $user->belongsToTenant($tenant)) {
            return $tenant;
        }

        $session->forget(self::SESSION_KEY);

        $tenants = $user->tenants()->active()->limit(2)->get();

        if ($tenants->count() !== 1) {
            return null;
        }

        $session->put(self::SESSION_KEY, $tenants->first()->getKey());

        return $tenants->first();
    }
}

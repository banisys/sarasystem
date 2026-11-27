<?php

namespace Modules\Course\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCourseToPrimaryTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $primary = Tenant::primary();

        if ($primary === null || $primary->is(Filament::getTenant())) {
            return $next($request);
        }

        $route = $request->route();
        $url = route($route->getName(), [...$route->originalParameters(), 'tenant' => $primary->slug]);
        $query = $request->server->get('QUERY_STRING', '');

        return redirect()->to($query === '' ? $url : $url.'?'.$query);
    }
}

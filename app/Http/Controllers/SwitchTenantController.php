<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchTenantController extends Controller
{
    /**
     * Make the given tenant the user's current tenant on the web side.
     */
    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user()->belongsToTenant($tenant), 403);

        $request->session()->put(ResolveCurrentTenant::SESSION_KEY, $tenant->getKey());

        return back();
    }
}

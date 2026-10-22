<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SwitchTenantController extends Controller
{
    /**
     * The tenant picker; the list comes from the shared `tenant.available` prop.
     */
    public function show(): Response
    {
        return Inertia::render('Tenants/Select');
    }

    /**
     * Make the given tenant the user's current tenant on the web side.
     */
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user()->belongsToTenant($tenant), 403);

        $request->session()->put(ResolveCurrentTenant::SESSION_KEY, $tenant->getKey());

        return redirect()->intended(route('dashboard'));
    }
}

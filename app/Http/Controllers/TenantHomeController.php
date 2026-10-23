<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public home pages (ADR 0009): the primary tenant's at the site root,
 * every other active tenant's at /{slug}.
 */
class TenantHomeController extends Controller
{
    public function primary(): Response
    {
        $tenant = Tenant::primary();

        return $tenant === null ? Inertia::render('Welcome') : $this->render($tenant);
    }

    public function show(Tenant $tenant): Response|RedirectResponse
    {
        abort_unless($tenant->is_active, 404);

        if ($tenant->is_primary) {
            return redirect()->route('home');
        }

        return $this->render($tenant);
    }

    private function render(Tenant $tenant): Response
    {
        return Inertia::render('Tenants/Home', [
            'home' => $tenant->only('name', 'slug', 'is_primary'),
            'otherTenants' => Tenant::query()->active()->whereKeyNot($tenant->getKey())->orderByDesc('is_primary')->orderBy('name')->get()
                ->map(fn (Tenant $other): array => ['name' => $other->name, 'url' => $other->homeUrl()])
                ->all(),
        ]);
    }
}

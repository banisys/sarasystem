<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => fn () => $request->user()?->only('id', 'name', 'email', 'mobile'),
                'canAccessAdmin' => fn (): bool => (bool) $request->user()?->canAccessPanel(Filament::getPanel('admin')),
            ],
            'tenant' => [
                'current' => fn () => Tenant::current()?->only('id', 'name', 'slug'),
                'available' => fn () => $this->availableTenants($request),
                'label' => config('sarasystem.tenant_label'),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }

    /**
     * Tenants the user may pick on the web side: those they hold a role in,
     * or every active tenant for a super admin.
     *
     * @return list<array{id: int, name: string, slug: string}>
     */
    private function availableTenants(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $query = $user->is_super_admin ? Tenant::query() : $user->tenants();

        return $query->active()->orderBy('name')->get(['tenants.id', 'tenants.name', 'tenants.slug'])
            ->map->only('id', 'name', 'slug')
            ->all();
    }
}

<?php

namespace App\Http\Controllers;

use App\PublicSite\PageResolver;
use App\PublicSite\PublicSite;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every public URL no other route claims (ADR 0009, ADR 0010): the tenant's
 * pages from the modules' PageResolvers, else the default home page for "/",
 * else 404. The primary tenant's own slug redirects to the same page without it.
 */
class PublicSiteController extends Controller
{
    public function __invoke(Request $request): Response
    {
        [$tenant, $path] = PublicSite::locate($request->path());

        if ($tenant === null) {
            abort_unless($path === '/', 404);

            return Inertia::render('Welcome')->toResponse($request);
        }

        if ($tenant->is_primary && $request->segment(1) === $tenant->slug) {
            return redirect()->to(PublicSite::url($tenant, $path));
        }

        /** @var PageResolver $resolver */
        foreach (app()->tagged(PublicSite::RESOLVER_TAG) as $resolver) {
            if ($response = $resolver->resolve($tenant, $path)) {
                return $response;
            }
        }

        abort_unless($path === '/', 404);

        return Inertia::render('Tenants/Home', PublicSite::layoutProps($tenant))->toResponse($request);
    }
}

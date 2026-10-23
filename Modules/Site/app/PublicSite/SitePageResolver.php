<?php

namespace Modules\Site\PublicSite;

use App\Models\Tenant;
use App\PublicSite\PageResolver;
use App\PublicSite\PublicSite;
use Inertia\Inertia;
use Modules\Site\Models\Page;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the tenant's published pages on its public site (ADR 0010).
 */
class SitePageResolver implements PageResolver
{
    public function resolve(Tenant $tenant, string $path): ?Response
    {
        $page = $tenant->execute(fn (): ?Page => Page::query()->where('path', $path)->where('is_published', true)->first());

        if ($page === null) {
            return null;
        }

        return Inertia::render('Site::Page', [
            ...PublicSite::layoutProps($tenant),
            'page' => $page->only('title', 'path'),
        ])->toResponse(request());
    }
}

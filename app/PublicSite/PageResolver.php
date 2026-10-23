<?php

namespace App\PublicSite;

use App\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a tenant's public path into a page (ADR 0010). Modules that publish
 * pages tag their implementation with PublicSite::RESOLVER_TAG; the core
 * never knows which modules do.
 */
interface PageResolver
{
    /**
     * @param  string  $path  "/" or "/about-us", always with a leading slash and no trailing one
     */
    public function resolve(Tenant $tenant, string $path): ?Response;
}

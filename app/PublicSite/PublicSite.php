<?php

namespace App\PublicSite;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Str;

/**
 * The public site of the installation (ADR 0009, ADR 0010): every active
 * tenant has pages under /{slug}/..., the primary tenant directly under /.
 */
class PublicSite
{
    public const RESOLVER_TAG = 'public-site.page-resolvers';

    /**
     * @var array<string, Closure(Tenant): list<array<string, mixed>>>
     */
    protected static array $navigationProviders = [];

    /**
     * Let a module supply links for the site header, e.g. the tenant's
     * menu (ADR 0012). Each link: {title, url (null = no link), new_tab,
     * children: [links]}. The links of all providers are shown in order.
     * Registering a name again replaces it (the app boots once per test).
     *
     * @param  Closure(Tenant): list<array<string, mixed>>  $callback
     */
    public static function navigationUsing(string $name, Closure $callback): void
    {
        static::$navigationProviders[$name] = $callback;
    }

    /**
     * Public URL of a path ("/" or "/about-us") on the tenant's site.
     */
    public static function url(Tenant $tenant, string $path = '/'): string
    {
        $path = '/'.trim($path, '/');

        if ($tenant->is_primary) {
            return url($path);
        }

        return url(rtrim('/'.$tenant->slug.$path, '/'));
    }

    /**
     * Split a request path into the tenant whose site it is and the path on
     * that site. A first segment naming a non-primary active tenant selects
     * that tenant; anything else is a path on the primary tenant's site.
     *
     * @return array{0: Tenant|null, 1: string}
     */
    public static function locate(string $requestPath): array
    {
        $path = '/'.trim($requestPath, '/');
        $firstSegment = Str::before(ltrim($path, '/'), '/');

        if ($firstSegment !== '') {
            $tenant = Tenant::query()->active()->where('slug', $firstSegment)->first();

            if ($tenant !== null) {
                return [$tenant, '/'.trim(Str::after(ltrim($path, '/'), $firstSegment), '/')];
            }
        }

        return [Tenant::primary(), $path];
    }

    /**
     * Props for the static header and footer (PublicLayout.vue).
     *
     * Under "site", because the shared "tenant" prop is the logged-in user's tenant.
     *
     * @return array{site: array{tenant: array{name: string, url: string}, navigation: list<array<string, mixed>>, otherTenants: list<array{name: string, url: string}>}}
     */
    public static function layoutProps(Tenant $tenant): array
    {
        return ['site' => [
            'tenant' => ['name' => $tenant->name, 'url' => self::url($tenant)],
            'navigation' => collect(static::$navigationProviders)->flatMap(fn (Closure $callback): array => $callback($tenant))->values()->all(),
            'otherTenants' => Tenant::query()->active()->whereKeyNot($tenant->getKey())->orderByDesc('is_primary')->orderBy('name')->get()
                ->map(fn (Tenant $other): array => ['name' => $other->name, 'url' => self::url($other)])
                ->all(),
        ]];
    }
}

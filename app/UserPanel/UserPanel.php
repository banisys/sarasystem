<?php

namespace App\UserPanel;

use App\Models\Tenant;
use App\Models\User;
use Closure;

/**
 * The user panel (Inertia, AppLayout.vue). Modules add links to its side
 * menu here, and may offer the page it opens on, since the core does not
 * know them (ADR 0005, ADR 0069).
 */
class UserPanel
{
    /**
     * @var array<string, Closure(User, Tenant): list<array{title: string, url: string, icon?: string}>>
     */
    protected static array $navigationProviders = [];

    /**
     * @var array<string, Closure(User, Tenant): ?string>
     */
    protected static array $homeProviders = [];

    /**
     * Let a module supply side-menu links for the user in the current tenant,
     * e.g. «آزمون‌ها». The links of all providers are shown in order.
     * Registering a name again replaces it (the app boots once per test).
     * `icon` names one of the menu's icons (AppLayout.vue), e.g. `calendar`.
     *
     * @param  Closure(User, Tenant): list<array{title: string, url: string, icon?: string}>  $callback
     */
    public static function navigationUsing(string $name, Closure $callback): void
    {
        static::$navigationProviders[$name] = $callback;
    }

    /**
     * @return list<array{title: string, url: string, icon?: string}>
     */
    public static function navigation(?User $user, ?Tenant $tenant): array
    {
        if ($user === null || $tenant === null) {
            return [];
        }

        return collect(static::$navigationProviders)->flatMap(fn (Closure $callback): array => $callback($user, $tenant))->values()->all();
    }

    /**
     * Let a module offer the page the panel opens on for the user, e.g. a
     * teacher's calendar of classes. The first provider (in registration
     * order) that gives a URL wins; with none, the panel opens on the
     * dashboard.
     *
     * @param  Closure(User, Tenant): ?string  $callback
     */
    public static function homeUsing(string $name, Closure $callback): void
    {
        static::$homeProviders[$name] = $callback;
    }

    public static function home(?User $user, ?Tenant $tenant): ?string
    {
        if ($user === null || $tenant === null) {
            return null;
        }

        foreach (static::$homeProviders as $callback) {
            if (($url = $callback($user, $tenant)) !== null) {
                return $url;
            }
        }

        return null;
    }
}

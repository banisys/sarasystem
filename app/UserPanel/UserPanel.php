<?php

namespace App\UserPanel;

use App\Models\Tenant;
use App\Models\User;
use Closure;

/**
 * The user panel (Inertia, AppLayout.vue). Modules add links to its header
 * here, since the core does not know them (ADR 0005).
 */
class UserPanel
{
    /**
     * @var array<string, Closure(User, Tenant): list<array{title: string, url: string}>>
     */
    protected static array $navigationProviders = [];

    /**
     * Let a module supply header links for the user in the current tenant,
     * e.g. «آزمون‌ها». The links of all providers are shown in order.
     * Registering a name again replaces it (the app boots once per test).
     *
     * @param  Closure(User, Tenant): list<array{title: string, url: string}>  $callback
     */
    public static function navigationUsing(string $name, Closure $callback): void
    {
        static::$navigationProviders[$name] = $callback;
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    public static function navigation(?User $user, ?Tenant $tenant): array
    {
        if ($user === null || $tenant === null) {
            return [];
        }

        return collect(static::$navigationProviders)->flatMap(fn (Closure $callback): array => $callback($user, $tenant))->values()->all();
    }
}

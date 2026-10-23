<?php

use App\Http\Middleware\EnsureTenantSelected;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => EnsureTenantSelected::class,
        ]);

        $middleware->web(append: [
            ResolveCurrentTenant::class,
            HandleInertiaRequests::class,
        ]);

        // The tenant must be current before route-model binding, or a
        // tenant-owned {model} is looked up with no tenant (fail-closed → 404).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveCurrentTenant::class);

        // Guests go to the shared login page (ADR 0004); logged-in users who
        // open a guest-only page (login) go to their dashboard.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

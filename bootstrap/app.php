<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveCurrentTenant::class,
            HandleInertiaRequests::class,
        ]);

        // TODO: point at the user-panel login once it exists; until then the
        // only login page is the admin panel's.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

<?php

use App\Http\Controllers\Auth\LeaveImpersonationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SendLoginCodeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SwitchTenantController;
use App\Http\Middleware\EnsureTenantSelected;
use App\Models\Tenant;
use App\UserPanel\UserPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', PublicSiteController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::post('/login/code', [LoginController::class, 'storeWithCode'])->name('login.code');
    Route::post('/login/code/send', SendLoginCodeController::class)
        ->middleware('throttle:10,1')
        ->name('login.code.send');
});

// auth.session logs a session out once the user's password changes elsewhere.
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/impersonation/leave', LeaveImpersonationController::class)->name('impersonation.leave');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/email', [ProfileController::class, 'updateEmail'])->name('profile.email');
    Route::post('/profile/mobile/code', [ProfileController::class, 'sendMobileCode'])
        ->middleware('throttle:10,1')
        ->name('profile.mobile.code');
    Route::put('/profile/mobile', [ProfileController::class, 'updateMobile'])->name('profile.mobile');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/tenants/select', [SwitchTenantController::class, 'show'])->name('tenants.select');
    Route::post('/tenants/{tenant:slug}/switch', [SwitchTenantController::class, 'store'])->name('tenants.switch');

    Route::middleware(EnsureTenantSelected::class)->group(function () {
        // The panel opens on a module's page when one is offered, e.g. a
        // teacher's calendar (ADR 0069); otherwise on the dashboard.
        Route::get('/dashboard', function (Request $request): RedirectResponse|Response {
            $home = UserPanel::home($request->user(), Tenant::current());

            return $home !== null ? redirect($home) : Inertia::render('Dashboard');
        })->name('dashboard');
    });
});

// Public site (ADR 0009, ADR 0010): /{tenant-slug}/... and the primary
// tenant's /... A fallback route, so every other route (modules' too) wins;
// Tenant::reservedSlugs() keeps tenant slugs off those routes.
Route::fallback(PublicSiteController::class);

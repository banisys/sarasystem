<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SendLoginCodeController;
use App\Http\Controllers\SwitchTenantController;
use App\Http\Middleware\EnsureTenantSelected;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::post('/login/code', [LoginController::class, 'storeWithCode'])->name('login.code');
    Route::post('/login/code/send', SendLoginCodeController::class)
        ->middleware('throttle:10,1')
        ->name('login.code.send');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/tenants/select', [SwitchTenantController::class, 'show'])->name('tenants.select');
    Route::post('/tenants/{tenant:slug}/switch', [SwitchTenantController::class, 'store'])->name('tenants.switch');

    Route::middleware(EnsureTenantSelected::class)->group(function () {
        Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    });
});

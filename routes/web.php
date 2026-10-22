<?php

use App\Http\Controllers\SwitchTenantController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::middleware('auth')->group(function () {
    Route::post('/tenants/{tenant:slug}/switch', SwitchTenantController::class)->name('tenants.switch');
});

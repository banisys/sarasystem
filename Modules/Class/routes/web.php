<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Class\Http\Controllers\MyClassController;
use Modules\Class\Http\Controllers\OnlineSessionController;
use Modules\Class\Http\Controllers\SessionMaterialController;

// Signed, short-lived links to sessions' materials (docs/decisions/0025-teaching-plans-and-session-content.md).
Route::middleware(['auth', 'signed'])->group(function () {
    Route::get('/class-materials/{media}', SessionMaterialController::class)->name('class.materials');
});

// Entering a session's online room, from either panel (docs/decisions/0073-online-class-platforms.md).
Route::middleware(['auth', 'auth.session'])->get('/online-classes/sessions/{session}', OnlineSessionController::class)
    ->whereNumber('session')
    ->name('class.online.enter');

// «تقویم آموزشی» in the user panel, inside the current tenant (docs/decisions/0026-my-classes.md).
Route::middleware(['auth', 'auth.session', 'tenant'])->prefix('profile/academic-calendar')->name('my-classes.')->group(function () {
    Route::get('/', [MyClassController::class, 'index'])->name('index');
    Route::get('/sessions/{session}', [MyClassController::class, 'show'])->name('session');
    Route::post('/sessions/{session}/attendance', [MyClassController::class, 'attendance'])->name('attendance');
    Route::post('/sessions/{session}/content', [MyClassController::class, 'content'])->name('content');
    Route::post('/sessions/{session}/homework', [MyClassController::class, 'submitHomework'])->name('homework');
    Route::post('/sessions/{session}/grades', [MyClassController::class, 'grade'])->name('grades');
});

// Preserve bookmarked calendar views and session details.
Route::middleware(['auth', 'auth.session', 'tenant'])->prefix('profile/my-classes')->group(function () {
    Route::get('/', fn (Request $request): RedirectResponse => to_route('my-classes.index', $request->query()));
    Route::get('/sessions/{session}', fn (Request $request, string $session): RedirectResponse => to_route(
        'my-classes.session', ['session' => $session] + $request->query(),
    ));
});

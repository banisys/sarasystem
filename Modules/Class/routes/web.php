<?php

use Illuminate\Support\Facades\Route;
use Modules\Class\Http\Controllers\MyClassController;
use Modules\Class\Http\Controllers\SessionMaterialController;

// Signed, short-lived links to sessions' materials (docs/decisions/0025-teaching-plans-and-session-content.md).
Route::middleware(['auth', 'signed'])->group(function () {
    Route::get('/class-materials/{media}', SessionMaterialController::class)->name('class.materials');
});

// «کلاس‌های من» in the user panel, inside the current tenant (docs/decisions/0026-my-classes.md).
Route::middleware(['auth', 'auth.session', 'tenant'])->prefix('my-classes')->name('my-classes.')->group(function () {
    Route::get('/', [MyClassController::class, 'index'])->name('index');
    Route::get('/sessions/{session}', [MyClassController::class, 'show'])->name('session');
    Route::post('/sessions/{session}/attendance', [MyClassController::class, 'attendance'])->name('attendance');
    Route::post('/sessions/{session}/content', [MyClassController::class, 'content'])->name('content');
});

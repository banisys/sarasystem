<?php

use Illuminate\Support\Facades\Route;
use Modules\Class\Http\Controllers\SessionMaterialController;

// Signed, short-lived links to sessions' materials (docs/decisions/0025-teaching-plans-and-session-content.md).
// The user panel's class pages come in a later step (docs/decisions/0022-classes.md).
Route::middleware(['auth', 'signed'])->group(function () {
    Route::get('/class-materials/{media}', SessionMaterialController::class)->name('class.materials');
});

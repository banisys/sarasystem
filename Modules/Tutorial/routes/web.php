<?php

use Illuminate\Support\Facades\Route;
use Modules\Tutorial\Http\Controllers\ActAsController;

// The step-by-step tutorial's role switch (ADR 0040).
Route::middleware(['auth', 'auth.session'])->post('/tutorial/act-as', ActAsController::class)->name('tutorial.act-as');

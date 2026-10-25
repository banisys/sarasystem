<?php

use Illuminate\Support\Facades\Route;
use Modules\Employee\Http\Controllers\CareersController;

// The public «همکاری با ما» form, on the primary tenant's site only (docs/decisions/0014-job-applications.md).
Route::get('/careers', [CareersController::class, 'create'])->name('careers.create');
Route::post('/careers', [CareersController::class, 'store'])->middleware('throttle:careers')->name('careers.store');
Route::post('/careers/mobile/code', [CareersController::class, 'sendCode'])->middleware('throttle:careers-mobile')->name('careers.mobile.code');
Route::post('/careers/mobile/verify', [CareersController::class, 'verifyCode'])->middleware('throttle:careers-mobile')->name('careers.mobile.verify');

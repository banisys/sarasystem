<?php

use Illuminate\Support\Facades\Route;
use Modules\Student\Http\Controllers\PreregistrationController;

// The public student «پیش‌ثبت‌نام» form, on the primary tenant's site only (docs/decisions/0018-student-preregistration.md).
Route::get('/preregister', [PreregistrationController::class, 'create'])->name('preregister.create');
Route::post('/preregister', [PreregistrationController::class, 'store'])->middleware('throttle:preregister')->name('preregister.store');
Route::post('/preregister/mobile/code', [PreregistrationController::class, 'sendCode'])->middleware('throttle:preregister-mobile')->name('preregister.mobile.code');
Route::post('/preregister/mobile/verify', [PreregistrationController::class, 'verifyCode'])->middleware('throttle:preregister-mobile')->name('preregister.mobile.verify');

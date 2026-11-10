<?php

use Illuminate\Support\Facades\Route;
use Modules\Exam\Http\Controllers\AttemptController;
use Modules\Exam\Http\Controllers\ContextExamController;
use Modules\Exam\Http\Controllers\ExamController;

// User-panel routes work inside the current tenant (docs/decisions/0002-tenancy-model.md).
Route::middleware(['auth', 'auth.session', 'tenant'])->prefix('exams')->name('exams.')->group(function () {
    Route::get('/', [ExamController::class, 'index'])->name('index');
    Route::get('/contexts/{type}/{context}', [ContextExamController::class, 'show'])->whereNumber('context')->name('context');
    Route::post('/contexts/{type}/{context}', [ContextExamController::class, 'store'])->whereNumber('context')->name('context.store');
    Route::patch('/contexts/{type}/{context}/exams/{exam}', [ContextExamController::class, 'publish'])->whereNumber('context')->name('context.publish');
    Route::get('/contexts/{type}/{context}/attempts/{attempt}', [ContextExamController::class, 'review'])->whereNumber('context')->name('context.review');
    Route::post('/contexts/{type}/{context}/attempts/{attempt}', [ContextExamController::class, 'grade'])->whereNumber('context')->name('context.grade');
    Route::get('/{exam}', [ExamController::class, 'show'])->name('show');
    Route::post('/{exam}/attempt', [AttemptController::class, 'store'])->name('attempt.start');
    Route::put('/{exam}/attempt', [AttemptController::class, 'update'])->name('attempt.save');
    Route::post('/{exam}/attempt/submit', [AttemptController::class, 'submit'])->name('attempt.submit');
});

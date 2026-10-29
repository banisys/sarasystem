<?php

use Illuminate\Support\Facades\Route;
use Modules\School\Http\Controllers\ReportCardController;
use Modules\School\Http\Controllers\ScoreBookController;

// The user panel's «دفتر نمره» and «کارنامه», inside the current tenant
// (docs/decisions/0032-scores-and-report-cards.md).
Route::middleware(['auth', 'auth.session', 'tenant'])->prefix('school')->name('school.')->group(function () {
    Route::get('/scores', [ScoreBookController::class, 'index'])->name('scores.index');
    Route::get('/scores/{classroom}/{subjectId}', [ScoreBookController::class, 'show'])->whereNumber('subjectId')->name('scores.show');
    Route::post('/scores/{classroom}/{subjectId}', [ScoreBookController::class, 'store'])->whereNumber('subjectId')->name('scores.store');
    Route::get('/report-cards', [ReportCardController::class, 'index'])->name('report-cards.index');
    Route::get('/report-cards/{enrollment}/{periodId}/pdf', [ReportCardController::class, 'pdf'])->whereNumber('periodId')->name('report-cards.pdf');
});

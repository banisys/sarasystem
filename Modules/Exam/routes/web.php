<?php

use Illuminate\Support\Facades\Route;
use Modules\Exam\Http\Controllers\AttemptController;
use Modules\Exam\Http\Controllers\ContextExamController;
use Modules\Exam\Http\Controllers\ExamController;
use Modules\Exam\Http\Controllers\QuestionLibraryController;

// User-panel routes work inside the current tenant (docs/decisions/0002-tenancy-model.md).
Route::middleware(['auth', 'auth.session', 'tenant'])->prefix('profile/exams')->name('exams.')->group(function () {
    Route::get('/', [ExamController::class, 'index'])->name('index');
    Route::prefix('library')->name('library')->controller(QuestionLibraryController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/banks', 'storeBank')->name('.banks.store');
        Route::patch('/banks/{bank}', 'updateBank')->name('.banks.update');
        Route::delete('/banks/{bank}', 'destroyBank')->name('.banks.destroy');
        Route::post('/questions', 'storeQuestion')->name('.questions.store');
        Route::patch('/questions/{question}', 'updateQuestion')->name('.questions.update');
        Route::delete('/questions/{question}', 'destroyQuestion')->name('.questions.destroy');
        Route::post('/questions/{question}/banks', 'addSharedQuestion')->name('.questions.banks.store');
    });
    Route::get('/contexts/{type}/{context}', [ContextExamController::class, 'show'])->whereNumber('context')->name('context');
    Route::post('/contexts/{type}/{context}', [ContextExamController::class, 'store'])->whereNumber('context')->name('context.store');
    Route::get('/contexts/{type}/{context}/banks/{bank}/questions', [ContextExamController::class, 'bankQuestions'])
        ->whereNumber(['context', 'bank'])->name('context.bank-questions');
    Route::patch('/contexts/{type}/{context}/exams/{exam}', [ContextExamController::class, 'publish'])->whereNumber('context')->name('context.publish');
    Route::patch('/contexts/{type}/{context}/exams/{exam}/settings', [ContextExamController::class, 'updateSettings'])
        ->whereNumber('context')->name('context.settings.update');
    Route::get('/contexts/{type}/{context}/exams/{exam}/questions', [ContextExamController::class, 'preview'])->whereNumber('context')->name('context.preview');
    Route::patch('/contexts/{type}/{context}/exams/{exam}/questions', [ContextExamController::class, 'reorder'])->whereNumber('context')->name('context.reorder');
    Route::delete('/contexts/{type}/{context}/exams/{exam}', [ContextExamController::class, 'destroy'])->whereNumber('context')->name('context.destroy');
    Route::get('/contexts/{type}/{context}/attempts/{attempt}', [ContextExamController::class, 'review'])->whereNumber('context')->name('context.review');
    Route::post('/contexts/{type}/{context}/attempts/{attempt}', [ContextExamController::class, 'grade'])->whereNumber('context')->name('context.grade');
    Route::get('/{exam}', [ExamController::class, 'show'])->name('show');
    Route::post('/{exam}/attempt', [AttemptController::class, 'store'])->name('attempt.start');
    Route::put('/{exam}/attempt', [AttemptController::class, 'update'])->name('attempt.save');
    Route::post('/{exam}/attempt/submit', [AttemptController::class, 'submit'])->name('attempt.submit');
});

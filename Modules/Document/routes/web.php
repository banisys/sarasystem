<?php

use Illuminate\Support\Facades\Route;
use Modules\Document\Http\Controllers\DocumentFileController;

// Signed, short-lived links to documents' files (docs/decisions/0019-documents.md).
Route::middleware(['auth', 'signed'])->group(function () {
    Route::get('/documents/files/{media}', DocumentFileController::class)->name('documents.file');
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\Course\Http\Controllers\CourseCatalogController;
use Modules\Course\Http\Controllers\CourseController;

Route::get('profile/courses/open/{branch:slug}/{catalogCourse}', [CourseCatalogController::class, 'open'])
    ->middleware(['auth', 'auth.session'])->whereNumber('catalogCourse')->name('courses.catalog.open');

Route::middleware(['auth', 'auth.session'])->prefix('profile/courses')->name('courses.')->scopeBindings()->group(function (): void {
    Route::get('/', [CourseController::class, 'index'])->name('index');
    Route::get('/{course}', [CourseController::class, 'show'])->name('show');
    Route::get('/{course}/cover', [CourseController::class, 'cover'])->name('cover');
    Route::post('/{course}/enroll', [CourseController::class, 'enroll'])->name('enroll');
    Route::get('/{course}/lessons/{lesson}', [CourseController::class, 'lesson'])->name('lesson');
    Route::put('/{course}/lessons/{lesson}/complete', [CourseController::class, 'complete'])->name('lesson.complete');
    Route::get('/{course}/lessons/{lesson}/files/{file}', [CourseController::class, 'file'])->whereNumber('file')->name('lesson.file');
});

Route::get('courses/{catalogCourse?}', CourseCatalogController::class)->whereNumber('catalogCourse')->name('courses.catalog');
Route::get('{branch}/courses/{catalogCourse?}', CourseCatalogController::class)->whereNumber('catalogCourse')->name('courses.catalog.branch');

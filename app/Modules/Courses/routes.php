<?php

use App\Modules\Courses\Http\Controllers\AnnouncementController;
use App\Modules\Courses\Http\Controllers\CatalogueController;
use App\Modules\Courses\Http\Controllers\CourseSpaceController;
use App\Modules\Courses\Http\Controllers\CourseSpaceStatusController;
use App\Modules\Courses\Http\Controllers\CourseStudentController;
use App\Modules\Courses\Http\Controllers\EnrolmentController;
use Illuminate\Support\Facades\Route;

/*
| Courses routes (web middleware group), on organisation subdomains only.
| {courseSpace} and {enrolment} bind only rows of the current tenant.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    Route::get('catalogue', CatalogueController::class)->name('courses.catalogue');

    Route::get('courses', [CourseSpaceController::class, 'index'])->name('courses.index');
    Route::get('courses/create', [CourseSpaceController::class, 'create'])->name('courses.create');
    Route::post('courses', [CourseSpaceController::class, 'store'])->name('courses.store');
    Route::get('courses/{courseSpace}', [CourseSpaceController::class, 'show'])->name('courses.show');
    Route::get('courses/{courseSpace}/edit', [CourseSpaceController::class, 'edit'])->name('courses.edit');
    Route::put('courses/{courseSpace}', [CourseSpaceController::class, 'update'])->name('courses.update');

    Route::post('courses/{courseSpace}/archive', [CourseSpaceStatusController::class, 'archive'])->name('courses.archive');
    Route::post('courses/{courseSpace}/restore', [CourseSpaceStatusController::class, 'restore'])->name('courses.restore');

    // SEC-010: enrolment key attempts are limited per user.
    Route::post('courses/{courseSpace}/enrolment', [EnrolmentController::class, 'store'])
        ->middleware('throttle:enrolment-key')
        ->name('courses.enrol');

    Route::post('courses/{courseSpace}/students', [CourseStudentController::class, 'store'])->name('courses.students.store');
    Route::delete('courses/{courseSpace}/students/{enrolment}', [CourseStudentController::class, 'destroy'])
        ->scopeBindings()
        ->name('courses.students.destroy');

    // UC-008: announcements are read on the course space page.
    Route::post('courses/{courseSpace}/announcements', [AnnouncementController::class, 'store'])->name('courses.announcements.store');
    Route::put('courses/{courseSpace}/announcements/{announcement}', [AnnouncementController::class, 'update'])
        ->scopeBindings()
        ->name('courses.announcements.update');
    Route::delete('courses/{courseSpace}/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
        ->scopeBindings()
        ->name('courses.announcements.destroy');
});

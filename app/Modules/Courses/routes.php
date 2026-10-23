<?php

use App\Modules\Courses\Http\Controllers\AnnouncementController;
use App\Modules\Courses\Http\Controllers\CatalogueController;
use App\Modules\Courses\Http\Controllers\CourseSpaceController;
use App\Modules\Courses\Http\Controllers\CourseSpaceStatusController;
use App\Modules\Courses\Http\Controllers\CourseStudentController;
use App\Modules\Courses\Http\Controllers\EnrolmentController;
use App\Modules\Courses\Http\Controllers\GroupController;
use App\Modules\Courses\Http\Controllers\GroupInvitationController;
use App\Modules\Courses\Http\Controllers\GroupMembershipController;
use Illuminate\Support\Facades\Route;

/*
| Courses routes (web middleware group), on organisation subdomains only.
| {courseSpace}, {enrolment}, {group} and {membership} bind only rows of the
| current tenant; {group} binds only groups that are not deleted.
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

    // UC-019: groups of a course space (SCOPE-025, OWN-005).
    Route::scopeBindings()->prefix('courses/{courseSpace}/groups')->name('courses.groups.')->group(function () {
        Route::get('/', [GroupController::class, 'index'])->name('index');
        Route::post('/', [GroupController::class, 'store'])->name('store');
        Route::get('{group}', [GroupController::class, 'show'])->name('show');
        Route::put('{group}', [GroupController::class, 'update'])->name('update');
        Route::delete('{group}', [GroupController::class, 'destroy'])->name('destroy');
        Route::post('{group}/owner', [GroupMembershipController::class, 'transfer'])->name('owner');
        Route::post('{group}/leave', [GroupMembershipController::class, 'leave'])->name('leave');
        Route::post('{group}/invitations', [GroupInvitationController::class, 'store'])->name('invitations.store');
        Route::post('{group}/invitations/{membership}/accept', [GroupInvitationController::class, 'accept'])->name('invitations.accept');
        Route::post('{group}/invitations/{membership}/decline', [GroupInvitationController::class, 'decline'])->name('invitations.decline');
        Route::delete('{group}/invitations/{membership}', [GroupInvitationController::class, 'destroy'])->name('invitations.destroy');
    });
});

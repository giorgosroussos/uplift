<?php

use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Http\Controllers\ActivityController;
use App\Modules\Activities\Http\Controllers\PollController;
use App\Modules\Activities\Http\Controllers\SubmissionController;
use App\Modules\Activities\Http\Controllers\TopicSelectionController;
use App\Modules\Activities\Http\Controllers\WizardStepController;
use Illuminate\Support\Facades\Route;

/*
| Activities routes (web middleware group), on organisation subdomains only.
| {activity} binds only activities of the current tenant. activities.store
| and activities.wizard.update are the JSON autosave endpoints and answer
| errors as RFC 9457 problem details.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('activities/create', [ActivityController::class, 'create'])->name('activities.create');
    Route::post('activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::get('activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');
    Route::get('activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit');
    Route::put('activities/{activity}/wizard/{step}', WizardStepController::class)
        ->whereIn('step', WizardStep::savedValues())
        ->name('activities.wizard.update');
    Route::post('activities/{activity}/publish', [ActivityController::class, 'publish'])->name('activities.publish');
    Route::post('activities/{activity}/close', [ActivityController::class, 'close'])->name('activities.close');
    Route::get('activities/{activity}/submissions', SubmissionController::class)->name('activities.submissions');

    // UC-013: a poll's page (vote and results); {activity} is the poll's activity.
    Route::get('polls/{activity}', [PollController::class, 'show'])->name('activities.polls.show');
    Route::post('polls/{activity}/vote', [PollController::class, 'vote'])->name('activities.polls.vote');

    // UC-014: a topic selection's page and the acting student's (or group's) choice.
    Route::get('topics/{activity}', [TopicSelectionController::class, 'show'])->name('activities.topics.show');
    Route::put('topics/{activity}/choice', [TopicSelectionController::class, 'choose'])->name('activities.topics.choose');
    Route::delete('topics/{activity}/choice', [TopicSelectionController::class, 'cancel'])->name('activities.topics.cancel');
});

<?php

use App\Modules\Live\Http\Controllers\LiveParticipationController;
use App\Modules\Live\Http\Controllers\LiveSessionController;
use Illuminate\Support\Facades\Route;

/*
| Live routes (web middleware group), on organisation subdomains only.
| {activity} is the live quiz's activity; {liveSession}, {liveParticipant}
| and {quizQuestion} bind only records of the current tenant.
| LiveSessionPolicy, LiveParticipantPolicy (OWN-012) and LiveAnswerPolicy
| (OWN-013) apply.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    // UC-024: the host teacher runs the live quiz.
    Route::post('live/quizzes/{activity}/lobby', [LiveSessionController::class, 'open'])->whereUuid('activity')->name('live.sessions.open');
    Route::post('live/sessions/{liveSession}/start', [LiveSessionController::class, 'start'])->whereUuid('liveSession')->name('live.sessions.start');
    Route::post('live/sessions/{liveSession}/cancel', [LiveSessionController::class, 'cancel'])->whereUuid('liveSession')->name('live.sessions.cancel');

    // UC-025: an enrolled student plays it.
    Route::post('live/quizzes/{activity}/join', [LiveParticipationController::class, 'join'])->whereUuid('activity')->name('live.join');
    // JSON save of one answer (RFC 9457 errors).
    Route::put('live/participants/{liveParticipant}/answers/{quizQuestion}', [LiveParticipationController::class, 'answer'])
        ->whereUuid(['liveParticipant', 'quizQuestion'])
        ->name('live.answers.update');
    Route::post('live/participants/{liveParticipant}/submit', [LiveParticipationController::class, 'submit'])->whereUuid('liveParticipant')->name('live.submit');
});

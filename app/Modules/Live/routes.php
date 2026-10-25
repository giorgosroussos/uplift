<?php

use App\Modules\Live\Http\Controllers\LiveParticipationController;
use App\Modules\Live\Http\Controllers\LiveSessionController;
use App\Modules\Live\Http\Controllers\LiveStateController;
use Illuminate\Support\Facades\Route;

/*
| Live routes (web middleware group), on organisation subdomains only.
| {activity} is the live quiz's activity; {liveSession}, {liveParticipant}
| and {quizQuestion} bind only records of the current tenant.
| LiveSessionPolicy, LiveParticipantPolicy (OWN-012) and LiveAnswerPolicy
| (OWN-013) apply.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    // UC-024: the host teacher runs the live quiz from its monitor page.
    Route::get('live/quizzes/{activity}', [LiveSessionController::class, 'monitor'])->whereUuid('activity')->name('live.monitor');
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

    // FLOW-002: JSON state snapshots (RFC 9457 errors), fetched on load,
    // after a reconnection and every 2 seconds while the websocket is down.
    Route::get('live/sessions/{liveSession}/state', [LiveStateController::class, 'host'])->whereUuid('liveSession')->name('live.sessions.state');
    Route::get('live/participants/{liveParticipant}/state', [LiveStateController::class, 'participant'])->whereUuid('liveParticipant')->name('live.participants.state');
});

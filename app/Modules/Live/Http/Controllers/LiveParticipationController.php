<?php

namespace App\Modules\Live\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Actions\JoinLiveQuiz;
use App\Modules\Live\Actions\SaveLiveAnswer;
use App\Modules\Live\Actions\SubmitLiveParticipation;
use App\Modules\Live\Models\LiveParticipant;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * UC-025: a student joins a live quiz, saves answers (JSON, RFC 9457
 * errors; over HTTP, never websocket client events, ARCH-006) and submits.
 * Route model binding only finds records of the current tenant;
 * LiveSessionPolicy (SEC-005) and LiveParticipantPolicy (OWN-012) decide
 * who may. The answer response never tells correctness (SCOPE-021).
 */
class LiveParticipationController extends Controller
{
    public function join(Request $request, Activity $activity, JoinLiveQuiz $join): RedirectResponse
    {
        Gate::authorize('join', [LiveSession::class, $activity]);

        /** @var User $user */
        $user = $request->user();
        $join->handle($user, $activity);

        return back();
    }

    public function answer(Request $request, LiveParticipant $liveParticipant, QuizQuestion $quizQuestion, SaveLiveAnswer $save): JsonResponse
    {
        Gate::authorize('update', $liveParticipant);

        $answer = $save->handle($liveParticipant, $quizQuestion, $request->all());

        return new JsonResponse(['saved_at' => $answer->answered_at->toIso8601String()]);
    }

    public function submit(LiveParticipant $liveParticipant, SubmitLiveParticipation $submit): RedirectResponse
    {
        Gate::authorize('update', $liveParticipant);

        $submit->handle($liveParticipant);

        return back();
    }
}

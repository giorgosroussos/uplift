<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Actions\SaveAnswer;
use App\Modules\Assessment\Actions\ShowAttempt;
use App\Modules\Assessment\Actions\ShowQuizForStudent;
use App\Modules\Assessment\Actions\StartQuizAttempt;
use App\Modules\Assessment\Actions\SubmitQuizAttempt;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-012: a student takes a self-paced quiz. Route model binding only
 * finds activities and attempts of the current tenant; QuizAttemptPolicy
 * applies OWN-006 and AnswerPolicy OWN-007. The answer autosave is JSON
 * and answers errors as RFC 9457 problem details.
 */
class QuizAttemptController extends Controller
{
    public function quiz(Request $request, Activity $activity, ShowQuizForStudent $show): Response
    {
        Gate::authorize('take', [QuizAttempt::class, $activity]);

        return Inertia::render('Assessment/Attempts/Quiz', $show->handle($this->user($request), $activity));
    }

    public function start(Request $request, Activity $activity, StartQuizAttempt $start): RedirectResponse
    {
        Gate::authorize('take', [QuizAttempt::class, $activity]);

        $attempt = $start->handle($this->user($request), $activity);

        return to_route('assessment.attempts.show', $attempt);
    }

    public function show(QuizAttempt $attempt, ShowAttempt $show): Response
    {
        Gate::authorize('update', $attempt);

        return Inertia::render('Assessment/Attempts/Take', $show->handle($attempt));
    }

    /**
     * Autosave of one answer (JSON).
     */
    public function answer(Request $request, QuizAttempt $attempt, QuizQuestion $quizQuestion, SaveAnswer $save): JsonResponse
    {
        Gate::authorize('update', $attempt);
        abort_unless($quizQuestion->quiz_id === $attempt->quiz_id, 404);

        $answer = $save->handle($attempt, $quizQuestion, $request->all());

        return new JsonResponse(['saved_at' => $answer->updated_at?->toIso8601String()]);
    }

    public function submit(Request $request, QuizAttempt $attempt, SubmitQuizAttempt $submit): RedirectResponse
    {
        Gate::authorize('update', $attempt);

        $submit->handle($attempt, $this->user($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.attempt.flash.submitted')]);

        return to_route('assessment.attempts.show', $attempt);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

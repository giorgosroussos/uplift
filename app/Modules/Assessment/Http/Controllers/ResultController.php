<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Actions\ListMyResults;
use App\Modules\Assessment\Actions\ShowResult;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-017: a student's own results. Route model binding only finds attempts
 * of the current tenant; QuizAttemptPolicy applies OWN-006.
 */
class ResultController extends Controller
{
    public function index(Request $request, ListMyResults $list): Response
    {
        Gate::authorize('viewAnyResults', QuizAttempt::class);

        /** @var User $student */
        $student = $request->user();

        return Inertia::render('Assessment/Results/Index', [
            'results' => $list->handle($student),
        ]);
    }

    public function show(QuizAttempt $attempt, ShowResult $show): Response|RedirectResponse
    {
        Gate::authorize('viewResult', $attempt);

        $result = $show->handle($attempt);

        if ($result === null) {
            // Still in progress: no result yet, continue the attempt.
            return to_route('assessment.attempts.show', $attempt);
        }

        return Inertia::render('Assessment/Results/Show', $result);
    }
}

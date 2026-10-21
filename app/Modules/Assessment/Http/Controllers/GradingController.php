<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Actions\GradeAnswer;
use App\Modules\Assessment\Actions\ShowAttemptForGrading;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-026 / UC-028: the owning teacher opens a submitted attempt from the
 * submissions overview and grades its answers. Route model binding only
 * finds attempts and answers of the current tenant; QuizAttemptPolicy
 * applies OWN-006 and AnswerPolicy OWN-007.
 */
class GradingController extends Controller
{
    public function show(QuizAttempt $attempt, ShowAttemptForGrading $show): Response
    {
        Gate::authorize('grade', $attempt);

        return Inertia::render('Assessment/Grading/Attempt', $show->handle($attempt));
    }

    public function update(Request $request, Answer $answer, GradeAnswer $grade): RedirectResponse
    {
        Gate::authorize('grade', $answer);

        /** @var User $teacher */
        $teacher = $request->user();

        $grade->handle($answer, $teacher, $request->only(['points', 'feedback']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.grading.flash.graded')]);

        return to_route('assessment.grading.show', $answer->attempt_id);
    }
}

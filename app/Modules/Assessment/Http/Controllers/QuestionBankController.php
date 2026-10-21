<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Actions\DeleteBankQuestion;
use App\Modules\Assessment\Actions\ListBankQuestions;
use App\Modules\Assessment\Actions\SaveBankQuestion;
use App\Modules\Assessment\Actions\ShowBankQuestion;
use App\Modules\Assessment\Http\Requests\QuestionRequest;
use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-023: the teacher's personal question bank. Route model binding only
 * finds questions of the current tenant; the policy applies OWN-016.
 */
class QuestionBankController extends Controller
{
    public function index(Request $request, ListBankQuestions $list): Response
    {
        Gate::authorize('viewAny', Question::class);

        return Inertia::render('Assessment/QuestionBank/Index', [
            'questions' => $list->handle($this->user($request)),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Question::class);

        return Inertia::render('Assessment/QuestionBank/Create');
    }

    public function store(QuestionRequest $request, SaveBankQuestion $save): RedirectResponse
    {
        Gate::authorize('create', Question::class);

        $save->handle($this->user($request), null, $request->question());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.flash.created')]);

        return to_route('assessment.questions.index');
    }

    public function edit(Question $question, ShowBankQuestion $show): Response
    {
        Gate::authorize('view', $question);

        return Inertia::render('Assessment/QuestionBank/Edit', [
            'question' => $show->handle($question),
        ]);
    }

    public function update(QuestionRequest $request, Question $question, SaveBankQuestion $save): RedirectResponse
    {
        Gate::authorize('update', $question);

        $save->handle($this->user($request), $question, $request->question());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.flash.updated')]);

        return to_route('assessment.questions.index');
    }

    public function destroy(Question $question, DeleteBankQuestion $delete): RedirectResponse
    {
        Gate::authorize('delete', $question);

        $delete->handle($question);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.flash.deleted')]);

        return to_route('assessment.questions.index');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

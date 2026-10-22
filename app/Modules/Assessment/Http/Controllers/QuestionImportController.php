<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assessment\Actions\BuildQuestionImportTemplate;
use App\Modules\Assessment\Actions\CancelQuestionImport;
use App\Modules\Assessment\Actions\ConfirmQuestionImport;
use App\Modules\Assessment\Actions\ShowQuestionImport;
use App\Modules\Assessment\Actions\StoreQuestionImport;
use App\Modules\Assessment\Http\Requests\QuestionImportRequest;
use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * UC-030 and DATA-017: bulk import of questions from a CSV file into the
 * teacher's own bank: upload, per-row report, confirm or cancel. OWN-016:
 * only active teachers; an import is found only under its uploader's own
 * directory (Support/QuestionImportFiles), so no one else confirms or
 * cancels it.
 */
class QuestionImportController extends Controller
{
    public function create(): Response
    {
        Gate::authorize('create', Question::class);

        return Inertia::render('Assessment/QuestionBank/Import');
    }

    public function template(BuildQuestionImportTemplate $template): HttpResponse
    {
        Gate::authorize('create', Question::class);

        return response($template->handle(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.BuildQuestionImportTemplate::FILE_NAME.'"',
        ]);
    }

    public function store(QuestionImportRequest $request, StoreQuestionImport $store): RedirectResponse
    {
        $id = $store->handle($this->user($request), $request->csv());

        return to_route('assessment.imports.show', ['import' => $id]);
    }

    public function show(Request $request, string $import, ShowQuestionImport $show): Response
    {
        Gate::authorize('create', Question::class);

        return Inertia::render('Assessment/QuestionBank/ImportReport', [
            'report' => $show->handle($this->user($request), $import),
        ]);
    }

    public function confirm(Request $request, string $import, ConfirmQuestionImport $confirm): RedirectResponse
    {
        Gate::authorize('create', Question::class);

        $result = $confirm->handle($this->user($request), $import);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.import.flash.imported', $result)]);

        return to_route('assessment.questions.index');
    }

    public function destroy(Request $request, string $import, CancelQuestionImport $cancel): RedirectResponse
    {
        Gate::authorize('create', Question::class);

        $cancel->handle($this->user($request), $import);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('assessment.import.flash.cancelled')]);

        return to_route('assessment.questions.index');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

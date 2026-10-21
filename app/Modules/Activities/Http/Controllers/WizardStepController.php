<?php

namespace App\Modules\Activities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Actions\SaveWizardStep;
use App\Modules\Activities\Actions\ShowWizard;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * UC-022: autosave of one wizard step (JSON). Errors are RFC 9457 problem
 * details: 422 with `errors` for invalid data, 409 when the change would
 * alter grading of existing submissions.
 */
class WizardStepController extends Controller
{
    public function __invoke(Request $request, Activity $activity, string $step, SaveWizardStep $save, ShowWizard $wizard): JsonResponse
    {
        Gate::authorize('update', $activity);

        /** @var array<string, mixed> $input */
        $input = $request->all();
        $save->handle($activity, WizardStep::from($step), $input);

        return new JsonResponse([
            'saved' => true,
            'steps' => $wizard->steps($activity->refresh()),
        ]);
    }
}

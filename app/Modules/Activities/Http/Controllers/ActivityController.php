<?php

namespace App\Modules\Activities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Actions\CloseActivity;
use App\Modules\Activities\Actions\CreateActivity;
use App\Modules\Activities\Actions\ListActivities;
use App\Modules\Activities\Actions\PublishActivity;
use App\Modules\Activities\Actions\ShowActivity;
use App\Modules\Activities\Actions\ShowWizard;
use App\Modules\Activities\Http\Requests\StoreActivityRequest;
use App\Modules\Activities\Models\Activity;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-022: activities of the user's course spaces and the creation wizard.
 * Route model binding only finds activities of the current tenant; the
 * ActivityPolicy applies the course space's ownership (OWN-004).
 */
class ActivityController extends Controller
{
    public function index(Request $request, ListActivities $list): Response
    {
        Gate::authorize('viewAny', Activity::class);
        $user = $this->user($request);
        $space = $request->query('course_space');

        return Inertia::render('Activities/Index', [
            'activities' => $list->handle($user, is_string($space) && Str::isUuid($space) ? $space : null),
            'canCreate' => $user->can('create', CourseSpace::class),
        ]);
    }

    public function show(Request $request, Activity $activity, ShowActivity $show): Response
    {
        Gate::authorize('view', $activity);

        return Inertia::render('Activities/Show', $show->handle($this->user($request), $activity));
    }

    /**
     * The wizard of a new activity; nothing is stored before the first autosave.
     */
    public function create(Request $request, ShowWizard $wizard): Response
    {
        Gate::authorize('create', CourseSpace::class);

        $space = $request->query('course_space');
        $type = $request->query('type');

        return Inertia::render('Activities/Wizard', $wizard->handle(
            $this->user($request),
            null,
            null,
            is_string($space) ? $space : null,
            is_string($type) ? $type : null,
        ));
    }

    /**
     * First autosave of the wizard (JSON): creates the draft.
     */
    public function store(StoreActivityRequest $request, CreateActivity $create, ShowWizard $wizard): JsonResponse
    {
        $space = CourseSpace::query()->findOrFail($request->validated('course_space_id'));
        Gate::authorize('create', [Activity::class, $space]);

        $activity = $create->handle($space, $request->kind(), $request->basics());

        return new JsonResponse([
            'id' => $activity->id,
            'edit_url' => route('activities.edit', $activity),
            'steps' => $wizard->steps($activity),
        ], 201);
    }

    public function edit(Request $request, Activity $activity, ShowWizard $wizard): Response
    {
        Gate::authorize('update', $activity);
        $step = $request->query('step');

        return Inertia::render('Activities/Wizard', $wizard->handle($this->user($request), $activity, is_string($step) ? $step : null));
    }

    public function publish(Request $request, Activity $activity, PublishActivity $publish): RedirectResponse
    {
        Gate::authorize('publish', $activity);

        $publish->handle($this->user($request), $activity);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('activities.flash.published')]);

        return to_route('activities.show', $activity);
    }

    public function close(Activity $activity, CloseActivity $close): RedirectResponse
    {
        Gate::authorize('close', $activity);

        $close->handle($activity);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('activities.flash.closed')]);

        return to_route('activities.show', $activity);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

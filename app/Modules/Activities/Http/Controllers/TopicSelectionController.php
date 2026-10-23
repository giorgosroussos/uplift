<?php

namespace App\Modules\Activities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Actions\CancelTopicChoice;
use App\Modules\Activities\Actions\SelectTopic;
use App\Modules\Activities\Actions\ShowTopicSelection;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-014: a topic selection's page, selecting (or switching) a topic and
 * cancelling the choice. Route model binding only finds activities of the
 * current tenant; TopicActivityPolicy decides who opens the page and who
 * chooses, and the actions only touch the acting student's own or group's
 * choice (OWN-008).
 */
class TopicSelectionController extends Controller
{
    public function show(Request $request, Activity $activity, ShowTopicSelection $show): Response
    {
        $topicActivity = $this->topicActivity($activity);
        Gate::authorize('view', $topicActivity);

        return Inertia::render('Activities/TopicSelection/Show', $show->handle($this->user($request), $topicActivity));
    }

    public function choose(Request $request, Activity $activity, SelectTopic $select): RedirectResponse
    {
        $topicActivity = $this->authorizeChoosing($activity);

        $select->handle($this->user($request), $topicActivity, $request->only('topic_id', 'group_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('activities.topics.flash.chosen')]);

        return to_route('activities.topics.show', $activity);
    }

    public function cancel(Request $request, Activity $activity, CancelTopicChoice $cancel): RedirectResponse
    {
        $topicActivity = $this->authorizeChoosing($activity);

        $cancel->handle($this->user($request), $topicActivity, $request->only('group_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('activities.topics.flash.cancelled')]);

        return to_route('activities.topics.show', $activity);
    }

    private function authorizeChoosing(Activity $activity): TopicActivity
    {
        $topicActivity = $this->topicActivity($activity);
        // A draft does not exist for students (404), like the activity itself.
        Gate::authorize('view', $topicActivity);
        Gate::authorize('choose', $topicActivity);

        return $topicActivity;
    }

    private function topicActivity(Activity $activity): TopicActivity
    {
        $topicActivity = $activity->type === ActivityKind::TopicSelection ? TopicActivity::forActivity($activity) : null;

        abort_if($topicActivity === null, 404);

        return $topicActivity;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

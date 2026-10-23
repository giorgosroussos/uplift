<?php

namespace App\Modules\Activities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Actions\DecideTopicProposal;
use App\Modules\Activities\Actions\ProposeTopic;
use App\Modules\Activities\Actions\ShowTopicProposals;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicProposal;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-015 and UC-027: a topic proposal activity's page, proposing a topic
 * and deciding a proposal. Route model binding only finds activities and
 * proposals of the current tenant; TopicActivityPolicy decides who opens
 * the page and who proposes, TopicProposalPolicy who decides a proposal
 * (OWN-009).
 */
class TopicProposalController extends Controller
{
    public function show(Request $request, Activity $activity, ShowTopicProposals $show): Response
    {
        $topicActivity = $this->topicActivity($activity);
        Gate::authorize('view', $topicActivity);

        return Inertia::render('Activities/TopicProposal/Show', $show->handle($this->user($request), $topicActivity));
    }

    public function propose(Request $request, Activity $activity, ProposeTopic $propose): RedirectResponse
    {
        $topicActivity = $this->topicActivity($activity);
        // A draft does not exist for students (404), like the activity itself.
        Gate::authorize('view', $topicActivity);
        Gate::authorize('propose', $topicActivity);

        $propose->handle($this->user($request), $topicActivity, $request->only('title', 'description', 'group_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('activities.proposals.flash.proposed')]);

        return to_route('activities.proposals.show', $activity);
    }

    public function decide(Request $request, Activity $activity, TopicProposal $proposal, DecideTopicProposal $decide): RedirectResponse
    {
        $topicActivity = $this->topicActivity($activity);
        abort_if($proposal->topic_activity_id !== $topicActivity->activity_id, 404);
        Gate::authorize('view', $topicActivity);
        Gate::authorize('decide', $proposal);

        $decided = $decide->handle($proposal, $request->only('decision', 'comment'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __("activities.proposals.flash.{$decided->status->value}")]);

        return to_route('activities.proposals.show', $activity);
    }

    private function topicActivity(Activity $activity): TopicActivity
    {
        abort_if($activity->type !== ActivityKind::TopicProposal, 404);

        return TopicActivity::forActivityOrNew($activity);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

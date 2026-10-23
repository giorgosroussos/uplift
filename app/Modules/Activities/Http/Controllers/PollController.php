<?php

namespace App\Modules\Activities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Actions\CastPollVote;
use App\Modules\Activities\Actions\ShowPoll;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-013: a poll's page and voting. Route model binding only finds
 * activities of the current tenant; PollPolicy decides who opens the poll,
 * votes and sees its results.
 */
class PollController extends Controller
{
    public function show(Request $request, Activity $activity, ShowPoll $show): Response
    {
        $poll = $this->poll($activity);
        Gate::authorize('view', $poll);

        return Inertia::render('Activities/Poll/Show', $show->handle($this->user($request), $poll));
    }

    public function vote(Request $request, Activity $activity, CastPollVote $cast): RedirectResponse
    {
        $poll = $this->poll($activity);
        // A draft does not exist for students (404), like the activity itself.
        Gate::authorize('view', $poll);
        Gate::authorize('vote', $poll);

        $cast->handle($this->user($request), $poll, $request->only('options'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('activities.poll.flash.voted')]);

        return to_route('activities.polls.show', $activity);
    }

    private function poll(Activity $activity): Poll
    {
        $poll = $activity->type === ActivityKind::Poll ? Poll::forActivity($activity) : null;

        abort_if($poll === null, 404);

        return $poll;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}

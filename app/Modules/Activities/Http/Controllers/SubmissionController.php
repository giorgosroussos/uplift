<?php

namespace App\Modules\Activities\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activities\Actions\ListSubmissions;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-028: the owning teacher reviews an activity's submissions, filtered by
 * status, and opens one to grade.
 */
class SubmissionController extends Controller
{
    public function __invoke(Request $request, Activity $activity, ListSubmissions $list): Response
    {
        Gate::authorize('viewSubmissions', $activity);

        $status = SubmissionStatus::tryFrom((string) $request->query('status', ''));

        return Inertia::render('Activities/Submissions', [
            'activity' => [
                'id' => $activity->id,
                'title' => $activity->title,
                'type' => $activity->type->value,
                'status' => $activity->status->value,
            ],
            'status' => $status?->value,
            'statuses' => SubmissionStatus::values(),
            'submissions' => $list->handle($activity, $status),
        ]);
    }
}

<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * What a user sees of an activity they may view: its basics and status,
 * and for the owning teacher the links to edit, close and review
 * submissions.
 */
class ShowActivity
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, Activity $activity): array
    {
        $gate = Gate::forUser($user);

        return [
            'activity' => [
                'id' => $activity->id,
                'title' => $activity->title,
                'description' => $activity->description,
                'type' => $activity->type->value,
                'status' => $activity->status->value,
                'course_space_id' => $activity->course_space_id,
                'course_space_title' => $activity->courseSpace->title,
                'deadline_at' => $activity->deadline_at?->toIso8601String(),
                'published_at' => $activity->published_at?->toIso8601String(),
            ],
            'can' => [
                'update' => $gate->allows('update', $activity),
                'publish' => $gate->allows('publish', $activity),
                'close' => $gate->allows('close', $activity),
                'viewSubmissions' => $gate->allows('viewSubmissions', $activity),
            ],
        ];
    }
}

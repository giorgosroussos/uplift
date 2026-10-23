<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * What a user sees of an activity they may view: its basics and status,
 * for the owning teacher the links to edit, close, review submissions and
 * delete it (while it may be deleted), and for an enrolled student whether
 * they take part.
 */
class ShowActivity
{
    public function __construct(private readonly ActivityTypes $types) {}

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
                // UC-040: a draft, or an activity nobody has submitted to yet.
                'delete' => $gate->allows('delete', $activity)
                    && ($activity->isDraft() || ! $this->hasSubmissions($activity)),
                // An enrolled student of an active space takes part (e.g. opens the quiz).
                'participate' => ! $activity->isDraft() && $gate->allows('participate', $activity->courseSpace),
            ],
        ];
    }

    private function hasSubmissions(Activity $activity): bool
    {
        return $this->types->has($activity->type) && $this->types->for($activity)->hasSubmissions($activity);
    }
}

<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Activities\Support\Submission;

/**
 * UC-028: the submissions of an activity as reported by its type, newest
 * first, optionally only those with one status (submitted, needs grading,
 * graded).
 */
class ListSubmissions
{
    public function __construct(private readonly ActivityTypes $types) {}

    /**
     * @return list<array{id: string, participant: string, submitted_at: string, status: string, earned_points: string|null, max_points: string|null, url: string|null}>
     */
    public function handle(Activity $activity, ?SubmissionStatus $status): array
    {
        if (! $this->types->has($activity->type)) {
            return [];
        }

        $submissions = array_values(array_filter(
            $this->types->for($activity)->submissions($activity, $status),
            static fn (Submission $submission): bool => $status === null || $submission->status === $status,
        ));

        usort($submissions, static fn (Submission $a, Submission $b): int => $b->submittedAt <=> $a->submittedAt);

        return array_map(static fn (Submission $submission): array => $submission->toArray(), $submissions);
    }
}

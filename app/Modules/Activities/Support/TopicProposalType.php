<?php

namespace App\Modules\Activities\Support;

use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicProposal;

/**
 * The topic proposal activity type (ARCH-004, UC-015, UC-027), registered
 * with the registry by ActivitiesServiceProvider. Its subtype row is
 * ENT-TopicActivity, created with the first proposal. It has neither
 * settings nor content of its own: the basics (title, description,
 * deadline, individual or group with its size) are all a proposal
 * activity needs, so the wizard goes from the basics to the review.
 *
 * Proposals are not graded; the owning teacher approves or rejects them.
 */
final class TopicProposalType implements ActivityType
{
    public function kind(): ActivityKind
    {
        return ActivityKind::TopicProposal;
    }

    public function component(WizardStep $step): ?string
    {
        return null;
    }

    public function stepData(Activity $activity, WizardStep $step): ?array
    {
        return null;
    }

    public function rules(Activity $activity, WizardStep $step): array
    {
        return [];
    }

    public function save(Activity $activity, WizardStep $step, array $data): void {}

    public function changesGrading(Activity $activity, WizardStep $step, array $data): bool
    {
        return false;
    }

    public function publishErrors(Activity $activity): array
    {
        return [];
    }

    /**
     * Any proposal, whatever its status (SCOPE-027).
     */
    public function hasSubmissions(Activity $activity): bool
    {
        return TopicProposal::query()->where('topic_activity_id', $activity->id)->exists();
    }

    /**
     * Every proposal, by when it was made. A proposal is not graded.
     */
    public function submissions(Activity $activity, ?SubmissionStatus $status): array
    {
        $topicActivity = TopicActivity::forActivity($activity);

        if ($topicActivity === null || ($status !== null && $status !== SubmissionStatus::Submitted)) {
            return [];
        }

        $url = route('activities.proposals.show', $activity);

        return $topicActivity->proposals()->with(['student', 'group'])->get()
            ->map(fn (TopicProposal $proposal): Submission => new Submission(
                id: $proposal->id,
                participant: $proposal->group_id !== null ? (string) $proposal->group?->name : (string) $proposal->student?->name,
                submittedAt: $proposal->created_at ?? now(),
                status: SubmissionStatus::Submitted,
                url: $url,
            ))
            ->values()
            ->all();
    }
}

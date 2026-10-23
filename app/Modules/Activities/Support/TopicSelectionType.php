<?php

namespace App\Modules\Activities\Support;

use App\Modules\Activities\Actions\SaveTopics;
use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicChoice;

/**
 * The topic selection activity type (ARCH-004, UC-014), registered with
 * the registry by ActivitiesServiceProvider. Its subtype row is
 * ENT-TopicActivity; it has no settings of its own, so the wizard shows
 * only its content step: the topics with their capacity (SCOPE-011).
 * Whether students choose alone or in groups, and the group size, are
 * basics of the activity.
 *
 * Topics do not grade anything; SaveTopics keeps held topics and enough
 * places for their holders instead of a grading lock.
 */
final class TopicSelectionType implements ActivityType
{
    public function __construct(private readonly SaveTopics $topics) {}

    public function kind(): ActivityKind
    {
        return ActivityKind::TopicSelection;
    }

    public function component(WizardStep $step): ?string
    {
        return $step === WizardStep::Content ? 'Activities/TopicSelection/Topics' : null;
    }

    public function stepData(Activity $activity, WizardStep $step): ?array
    {
        return $step === WizardStep::Content ? SaveTopics::data(TopicActivity::forActivity($activity)) : null;
    }

    public function rules(Activity $activity, WizardStep $step): array
    {
        return $step === WizardStep::Content
            ? ['topics' => ['present', 'array', 'list'], 'topics.*' => ['array']]
            : [];
    }

    public function save(Activity $activity, WizardStep $step, array $data): void
    {
        if ($step === WizardStep::Content) {
            $this->topics->handle($activity, $data);
        }
    }

    public function changesGrading(Activity $activity, WizardStep $step, array $data): bool
    {
        return false;
    }

    public function publishErrors(Activity $activity): array
    {
        $topicActivity = TopicActivity::forActivity($activity);

        if ($topicActivity === null || ! $topicActivity->topics()->exists()) {
            return ['topics' => (string) __('activities.topics.errors.min_topics', ['min' => SaveTopics::MIN_TOPICS])];
        }

        return [];
    }

    public function hasSubmissions(Activity $activity): bool
    {
        $topicActivity = TopicActivity::forActivity($activity);

        return $topicActivity !== null && $topicActivity->choices()->exists();
    }

    /**
     * Every current choice: the student or group that holds a topic, since
     * the last change of the choice. A choice is not graded.
     */
    public function submissions(Activity $activity, ?SubmissionStatus $status): array
    {
        $topicActivity = TopicActivity::forActivity($activity);

        if ($topicActivity === null) {
            return [];
        }

        $url = route('activities.topics.show', $activity);

        return $topicActivity->choices()->with(['student', 'group', 'topic'])->get()
            ->map(fn (TopicChoice $choice): Submission => new Submission(
                id: $choice->id,
                participant: $choice->group_id !== null ? (string) $choice->group?->name : (string) $choice->student?->name,
                submittedAt: $choice->updated_at ?? $choice->created_at ?? now(),
                status: SubmissionStatus::Submitted,
                url: $url,
            ))
            ->values()
            ->all();
    }
}

<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Topic;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicChoice;
use App\Modules\Activities\Support\ActingGroups;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * UC-014 step 1: a topic selection as its viewer may see it. Everybody who
 * opens it sees each topic with its remaining capacity. A student sees
 * only their own choice, or in a group activity the choices of the groups
 * they are a current member of (OWN-008); the owning teacher also sees who
 * holds each topic.
 */
class ShowTopicSelection
{
    public function __construct(private readonly ActingGroups $groups) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, TopicActivity $topicActivity): array
    {
        $activity = $topicActivity->activity;
        $gate = Gate::forUser($user);
        $canChoose = $gate->allows('choose', $topicActivity);
        $showHolders = $gate->allows('viewChoices', $topicActivity);

        /** @var array<string, list<TopicChoice>> $choicesByTopic */
        $choicesByTopic = [];

        foreach ($topicActivity->choices()->with(['student', 'group'])->get() as $choice) {
            $choicesByTopic[$choice->topic_id][] = $choice;
        }

        $topics = $topicActivity->topics()->get()->map(function (Topic $topic) use ($choicesByTopic, $showHolders): array {
            $choices = $choicesByTopic[$topic->id] ?? [];
            $row = [
                'id' => $topic->id,
                'title' => $topic->title,
                'description' => $topic->description,
                'capacity' => $topic->capacity,
                'remaining' => max(0, $topic->capacity - count($choices)),
            ];

            if ($showHolders) {
                $row['holders'] = array_map(static fn (TopicChoice $choice): string => self::holderName($choice), $choices);
            }

            return $row;
        })->values()->all();

        return [
            'topicSelection' => [
                'activity_id' => $activity->id,
                'title' => $activity->title,
                'description' => $activity->description,
                'status' => $activity->status->value,
                'course_space_title' => $activity->courseSpace->title,
                'deadline_at' => $activity->deadline_at?->toIso8601String(),
                'is_group' => $activity->is_group,
                'group_size' => $activity->group_size,
                'is_open' => $topicActivity->isOpen(),
                'topics' => $topics,
            ],
            'choice' => $canChoose && ! $activity->is_group ? $this->ownChoice($user, $topicActivity) : null,
            'groups' => $canChoose && $activity->is_group ? $this->groupChoices($user, $topicActivity) : [],
            'can' => [
                'choose' => $canChoose && $topicActivity->isOpen(),
                'viewHolders' => $showHolders,
                'update' => $gate->allows('update', $activity),
            ],
        ];
    }

    /**
     * @return array{topic_id: string}|null
     */
    private function ownChoice(User $student, TopicActivity $topicActivity): ?array
    {
        $choice = SelectTopic::currentChoice($topicActivity, ['student_id' => (string) $student->getKey(), 'group_id' => null]);

        return $choice === null ? null : ['topic_id' => $choice->topic_id];
    }

    /**
     * The student's current groups in the space with their choice and
     * whether they may act in this activity (SCOPE-025).
     *
     * @return list<array{id: string, name: string, members: int, topic_id: string|null, too_large: bool, in_other_group: bool}>
     */
    private function groupChoices(User $student, TopicActivity $topicActivity): array
    {
        $activity = $topicActivity->activity;
        $participating = $topicActivity->choices()->whereNotNull('group_id')->pluck('topic_id', 'group_id')
            ->mapWithKeys(fn (mixed $topicId, mixed $groupId): array => [(string) $groupId => (string) $topicId])
            ->all();

        return $this->groups->of($student, $activity)
            ->map(fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'members' => count($this->groups->memberIds($group)),
                'topic_id' => $participating[$group->id] ?? null,
                'too_large' => $this->groups->tooLarge($group, $activity),
                'in_other_group' => $this->groups->overlaps($group, array_map('strval', array_keys($participating))),
            ])
            ->values()
            ->all();
    }

    private static function holderName(TopicChoice $choice): string
    {
        if ($choice->group_id !== null) {
            return (string) $choice->group?->name;
        }

        return (string) $choice->student?->name;
    }
}

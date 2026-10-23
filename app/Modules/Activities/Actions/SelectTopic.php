<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Topic;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicChoice;
use App\Modules\Activities\Support\ActingGroups;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UC-014 steps 2-3: a student selects a topic with free capacity, or
 * switches to another one, until the deadline (SCOPE-011). In a group
 * activity the student chooses which of their groups acts and the choice
 * belongs to that group (SCOPE-012, SCOPE-025); the group may not have more
 * current members than the activity's group_size, nor share a current
 * member with another group that already holds a topic in the activity.
 *
 * The activity row is locked for the whole decision, so concurrent
 * selections in the same activity are serialised: a topic never gets more
 * holders than its capacity, and a holder never holds two topics.
 */
class SelectTopic
{
    public function __construct(private readonly ActingGroups $groups) {}

    /**
     * @param  array<string, mixed>  $input  {topic_id: uuid, group_id?: uuid}
     *
     * @throws ValidationException (field "topic" or "group_id") when the selection is refused
     */
    public function handle(User $student, TopicActivity $topicActivity, array $input): TopicChoice
    {
        /** @var array{topic_id: string, group_id?: string|null} $data */
        $data = Validator::make($input, [
            'topic_id' => ['required', 'uuid'],
            'group_id' => ['nullable', 'uuid'],
        ], [
            'topic_id.required' => (string) __('activities.topics.choose.errors.choose'),
        ], [
            'topic_id' => (string) __('activities.topics.attributes.topic'),
            'group_id' => (string) __('activities.topics.attributes.group'),
        ])->validate();

        return DB::transaction(function () use ($student, $topicActivity, $data): TopicChoice {
            // Serialises every choice of the activity, and against closing and editing it.
            $activity = Activity::query()->lockForUpdate()->findOrFail($topicActivity->activity_id);
            $locked = TopicActivity::query()->findOrFail($topicActivity->activity_id);
            $locked->setRelation('activity', $activity);

            self::ensureOpen($locked);

            $topic = Topic::query()
                ->where('topic_activity_id', $locked->activity_id)
                ->find($data['topic_id']);

            if ($topic === null) {
                self::refuse('unknown_topic');
            }

            $holder = $this->holder($student, $locked, $data['group_id'] ?? null);
            $current = self::currentChoice($locked, $holder);

            // OWN-008: only the student's own or their group's choice changes.
            if ($current !== null && Gate::forUser($student)->denies('update', $current)) {
                self::refuse('not_yours');
            }

            if ($current !== null && $current->topic_id === $topic->id) {
                return $current;
            }

            if ($topic->choices()->count() >= $topic->capacity) {
                self::refuse('full');
            }

            $choice = $current ?? new TopicChoice;
            $choice->organization_id = $locked->organization_id;
            $choice->student_id = $holder['student_id'];
            $choice->group_id = $holder['group_id'];
            $choice->topic_id = $topic->id;
            $choice->save();

            return $choice;
        });
    }

    /**
     * Who holds the choice: the student in an individual activity, else the
     * group the student acts for.
     *
     * @return array{student_id: string|null, group_id: string|null}
     *
     * @throws ValidationException
     */
    private function holder(User $student, TopicActivity $topicActivity, ?string $groupId): array
    {
        $activity = $topicActivity->activity;

        if (! $activity->is_group) {
            return ['student_id' => (string) $student->getKey(), 'group_id' => null];
        }

        if ($groupId === null) {
            self::refuse('choose_group', 'group_id');
        }

        $group = $this->groups->find($student, $activity, $groupId);

        if ($group === null) {
            self::refuse('not_your_group', 'group_id');
        }

        if ($this->groups->tooLarge($group, $activity)) {
            self::refuse('group_too_large', 'group_id', ['size' => (int) $activity->group_size]);
        }

        $participating = $topicActivity->choices()->whereNotNull('group_id')->pluck('group_id')
            ->map(fn (mixed $id): string => (string) $id)->values()->all();

        if ($this->groups->overlaps($group, $participating)) {
            self::refuse('member_in_other_group', 'group_id');
        }

        return ['student_id' => null, 'group_id' => $group->id];
    }

    /**
     * @param  array{student_id: string|null, group_id: string|null}  $holder
     */
    public static function currentChoice(TopicActivity $topicActivity, array $holder): ?TopicChoice
    {
        $query = $topicActivity->choices();

        return $holder['group_id'] !== null
            ? $query->where('group_id', $holder['group_id'])->first()
            : $query->where('student_id', $holder['student_id'])->first();
    }

    /**
     * Choices change only while the activity is published and before its
     * deadline (SCOPE-011, server time).
     *
     * @throws ValidationException
     */
    public static function ensureOpen(TopicActivity $topicActivity): void
    {
        if ($topicActivity->isOpen()) {
            return;
        }

        self::refuse($topicActivity->activity->isPublished() ? 'deadline_passed' : 'closed');
    }

    /**
     * @param  array<string, int|string>  $replace
     *
     * @throws ValidationException
     */
    public static function refuse(string $reason, string $field = 'topic', array $replace = []): never
    {
        throw ValidationException::withMessages([
            $field => (string) __("activities.topics.choose.refused.{$reason}", $replace),
        ]);
    }
}

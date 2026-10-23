<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Topic;
use App\Modules\Activities\Models\TopicActivity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UC-022 step 3 for a topic selection (wizard step "content"): the topics
 * with their capacity (SCOPE-011). The client identifies every topic by a
 * UUID, which it generates for new ones, so autosaving the same list again
 * changes nothing; an id of a topic outside this activity is refused.
 * Topics left out are deleted.
 *
 * Once topics are held, a held topic cannot be removed and its capacity
 * cannot drop below its holders: there are never more holders than places.
 * Called by the type inside the wizard's transaction, which locks the
 * activity row like SelectTopic does.
 */
class SaveTopics
{
    public const int MIN_TOPICS = 1;

    public const int MAX_TOPICS = 100;

    public const int MAX_CAPACITY = 1000;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Activity $activity, array $input): void
    {
        $topicActivity = TopicActivity::forActivity($activity);
        $topics = $this->validate($topicActivity, $input);

        if ($topicActivity === null) {
            $topicActivity = new TopicActivity;
            $topicActivity->activity_id = $activity->id;
            $topicActivity->organization_id = $activity->organization_id;
            $topicActivity->save();
        }

        /** @var array<string, Topic> $existing */
        $existing = $topicActivity->topics()->get()->keyBy('id')->all();

        foreach ($topics as $item) {
            $topic = $existing[$item['id']] ?? null;

            if ($topic === null) {
                $topic = new Topic;
                $topic->id = $item['id'];
                $topic->organization_id = $topicActivity->organization_id;
                $topic->topic_activity_id = $topicActivity->activity_id;
            }

            $topic->fill([
                'title' => $item['title'],
                'description' => $item['description'],
                'capacity' => $item['capacity'],
            ])->save();
        }

        $removed = array_values(array_diff(array_keys($existing), array_column($topics, 'id')));

        if ($removed !== []) {
            Topic::query()->where('topic_activity_id', $topicActivity->activity_id)->whereKey($removed)->delete();
        }
    }

    /**
     * The saved topics in the shape of the wizard step, or null when the
     * activity has no topics yet.
     *
     * @return array{topics: list<array{id: string, title: string, description: string|null, capacity: int}>}|null
     */
    public static function data(?TopicActivity $topicActivity): ?array
    {
        if ($topicActivity === null) {
            return null;
        }

        $topics = $topicActivity->topics()->get()
            ->map(fn (Topic $topic): array => [
                'id' => $topic->id,
                'title' => $topic->title,
                'description' => $topic->description,
                'capacity' => $topic->capacity,
            ])
            ->values()
            ->all();

        return $topics === [] ? null : ['topics' => $topics];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{id: string, title: string, description: string|null, capacity: int}>
     *
     * @throws ValidationException
     */
    private function validate(?TopicActivity $topicActivity, array $input): array
    {
        $validator = Validator::make($input, [
            'topics' => ['present', 'array', 'list', 'min:'.self::MIN_TOPICS, 'max:'.self::MAX_TOPICS],
            'topics.*' => ['array'],
            'topics.*.id' => ['required', 'uuid', 'distinct'],
            'topics.*.title' => ['required', 'string', 'max:255'],
            'topics.*.description' => ['nullable', 'string', 'max:10000'],
            'topics.*.capacity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CAPACITY],
        ], [
            'topics.min' => (string) __('activities.topics.errors.min_topics', ['min' => self::MIN_TOPICS]),
            'topics.max' => (string) __('activities.topics.errors.max_topics', ['max' => self::MAX_TOPICS]),
        ], [
            'topics' => (string) __('activities.topics.attributes.topics'),
            'topics.*.id' => (string) __('activities.topics.attributes.topic'),
            'topics.*.title' => (string) __('activities.topics.attributes.title'),
            'topics.*.description' => (string) __('activities.topics.attributes.description'),
            'topics.*.capacity' => (string) __('activities.topics.attributes.capacity'),
        ]);

        /** @var array{topics: list<array{id: string, title: string, description?: string|null, capacity: int|string}>} $data */
        $data = $validator->validate();
        $topics = array_map(static fn (array $topic): array => [
            'id' => (string) $topic['id'],
            'title' => trim((string) $topic['title']),
            'description' => isset($topic['description']) && trim((string) $topic['description']) !== '' ? (string) $topic['description'] : null,
            'capacity' => (int) $topic['capacity'],
        ], $data['topics']);

        /** @var array<string, int> $holders topic id => number of holders */
        $holders = [];
        $ownIds = [];

        if ($topicActivity !== null) {
            foreach ($topicActivity->topics()->withCount('choices')->get() as $topic) {
                $ownIds[] = $topic->id;
                $holders[$topic->id] = (int) $topic->getAttribute('choices_count');
            }
        }

        $newIds = array_values(array_diff(array_column($topics, 'id'), $ownIds));
        $taken = $newIds === [] ? [] : Topic::query()->withoutGlobalScopes()->whereKey($newIds)
            ->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
        $errors = [];

        foreach ($topics as $index => $topic) {
            // A primary key is global: only the activity's own topics may be addressed.
            if (in_array($topic['id'], $taken, true)) {
                $errors["topics.{$index}.id"] = [(string) __('activities.topics.errors.foreign_id')];
            }

            // SCOPE-011: never fewer places than holders.
            $held = $holders[$topic['id']] ?? 0;

            if ($topic['capacity'] < $held) {
                $errors["topics.{$index}.capacity"] = [(string) __('activities.topics.errors.capacity_below_holders', ['count' => $held])];
            }
        }

        $kept = array_column($topics, 'id');

        foreach ($holders as $id => $held) {
            if ($held > 0 && ! in_array($id, $kept, true)) {
                $errors['topics'] = [(string) __('activities.topics.errors.remove_held')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $topics;
    }
}

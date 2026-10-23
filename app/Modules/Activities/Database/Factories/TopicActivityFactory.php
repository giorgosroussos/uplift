<?php

namespace App\Modules\Activities\Database\Factories;

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Topic;
use App\Modules\Activities\Models\TopicActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Topic activities (the subtype row) of a new draft topic selection unless
 * ->for($activity, 'activity') is given; ->withTopics() adds topics.
 *
 * @extends Factory<TopicActivity>
 */
class TopicActivityFactory extends Factory
{
    protected $model = TopicActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => fn (): string => (string) Activity::factory()->ofKind(ActivityKind::TopicSelection)->create()->getKey(),
            'organization_id' => fn (array $attributes): string => Activity::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['activity_id'])
                ->value('organization_id'),
        ];
    }

    /**
     * @param  array<string, int>  $topics  title => capacity
     */
    public function withTopics(array $topics = ['A' => 1, 'B' => 1]): static
    {
        return $this->afterCreating(function (TopicActivity $topicActivity) use ($topics): void {
            foreach ($topics as $title => $capacity) {
                $topic = new Topic(['title' => (string) $title, 'capacity' => $capacity]);
                $topic->organization_id = $topicActivity->organization_id;
                $topic->topic_activity_id = $topicActivity->activity_id;
                $topic->save();
            }
        });
    }
}

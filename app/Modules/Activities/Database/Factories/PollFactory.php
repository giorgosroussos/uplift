<?php

namespace App\Modules\Activities\Database\Factories;

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\PollResultsVisibility;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\Poll;
use App\Modules\Activities\Models\PollOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Polls (the subtype row) of a new draft poll activity unless
 * ->for($activity, 'activity') is given; ->withOptions() adds options in
 * order.
 *
 * @extends Factory<Poll>
 */
class PollFactory extends Factory
{
    protected $model = Poll::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => fn (): string => (string) Activity::factory()->ofKind(ActivityKind::Poll)->create()->getKey(),
            'organization_id' => fn (array $attributes): string => Activity::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['activity_id'])
                ->value('organization_id'),
            'max_choices' => 1,
            'results_visibility' => PollResultsVisibility::AfterVote,
        ];
    }

    public function afterClose(): static
    {
        return $this->state(['results_visibility' => PollResultsVisibility::AfterClose]);
    }

    /**
     * @param  list<string>  $labels
     */
    public function withOptions(array $labels = ['A', 'B', 'C']): static
    {
        return $this->afterCreating(function (Poll $poll) use ($labels): void {
            foreach ($labels as $position => $label) {
                $option = new PollOption(['label' => $label, 'position' => $position]);
                $option->organization_id = $poll->organization_id;
                $option->poll_id = $poll->activity_id;
                $option->save();
            }
        });
    }
}

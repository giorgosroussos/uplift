<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Support\ActingGroups;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UC-014 step 3: a student cancels their own topic choice, or the choice
 * of a group they are a current member of, until the deadline
 * (SCOPE-011); the topic regains the place. After the deadline, or once
 * the activity is closed, the cancellation is refused and nothing changes.
 */
class CancelTopicChoice
{
    public function __construct(private readonly ActingGroups $groups) {}

    /**
     * @param  array<string, mixed>  $input  {group_id?: uuid}
     *
     * @throws ValidationException when the cancellation is refused
     */
    public function handle(User $student, TopicActivity $topicActivity, array $input): void
    {
        /** @var array{group_id?: string|null} $data */
        $data = Validator::make($input, [
            'group_id' => ['nullable', 'uuid'],
        ], [], [
            'group_id' => (string) __('activities.topics.attributes.group'),
        ])->validate();

        DB::transaction(function () use ($student, $topicActivity, $data): void {
            $activity = Activity::query()->lockForUpdate()->findOrFail($topicActivity->activity_id);
            $locked = TopicActivity::query()->findOrFail($topicActivity->activity_id);
            $locked->setRelation('activity', $activity);

            SelectTopic::ensureOpen($locked);

            if ($activity->is_group) {
                $groupId = $data['group_id'] ?? null;
                $group = $groupId === null ? null : $this->groups->find($student, $activity, $groupId);

                if ($group === null) {
                    SelectTopic::refuse('not_your_group', 'group_id');
                }

                $holder = ['student_id' => null, 'group_id' => $group->id];
            } else {
                $holder = ['student_id' => (string) $student->getKey(), 'group_id' => null];
            }

            $choice = SelectTopic::currentChoice($locked, $holder);

            if ($choice === null) {
                SelectTopic::refuse('no_choice');
            }

            // OWN-008: only the student's own or their group's choice is cancelled.
            if (Gate::forUser($student)->denies('delete', $choice)) {
                SelectTopic::refuse('not_yours');
            }

            $choice->delete();
        });
    }
}

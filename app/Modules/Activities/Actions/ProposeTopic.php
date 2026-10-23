<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\TopicProposalStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicProposal;
use App\Modules\Activities\Support\ActingGroups;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * UC-015 steps 1-2: a student, or in a group activity one member for the
 * group (SCOPE-012), proposes a topic with a title and a description
 * before the deadline; the proposal is pending until the teacher decides.
 * A holder with a pending proposal cannot propose again until it is
 * decided; after a rejection they may propose anew, after an approval
 * never again in that activity.
 *
 * In a group activity the student chooses which of their groups proposes
 * (SCOPE-025); the group may not have more current members than the
 * activity's group_size, nor share a current member with another group
 * that already takes part (has a proposal) in the activity.
 *
 * The activity row is locked for the whole decision, so concurrent
 * proposals of one holder are serialised; the partial unique indexes on
 * topic_proposals guarantee it in the database as well.
 */
class ProposeTopic
{
    public const int MAX_DESCRIPTION = 5000;

    public function __construct(private readonly ActingGroups $groups) {}

    /**
     * @param  array<string, mixed>  $input  {title: string, description?: string, group_id?: uuid}
     *
     * @throws ValidationException (field "proposal" or "group_id" when refused, else the input field)
     */
    public function handle(User $student, TopicActivity $topicActivity, array $input): TopicProposal
    {
        /** @var array{title: string, description?: string|null, group_id?: string|null} $data */
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:'.self::MAX_DESCRIPTION],
            'group_id' => ['nullable', 'uuid'],
        ], [], [
            'title' => (string) __('activities.proposals.attributes.title'),
            'description' => (string) __('activities.proposals.attributes.description'),
            'group_id' => (string) __('activities.proposals.attributes.group'),
        ])->validate();

        try {
            return DB::transaction(function () use ($student, $topicActivity, $data): TopicProposal {
                // Serialises every proposal of the activity, and against closing it.
                $activity = Activity::query()->lockForUpdate()->findOrFail($topicActivity->activity_id);
                $locked = self::subtypeRow($activity);

                if (! $locked->isOpen()) {
                    self::refuse($activity->isPublished() ? 'deadline_passed' : 'closed');
                }

                $holder = $this->holder($student, $locked, $data['group_id'] ?? null);
                $current = self::holderProposals($locked, $holder)
                    ->whereIn('status', [TopicProposalStatus::Pending, TopicProposalStatus::Approved])
                    ->first();

                if ($current !== null) {
                    self::refuse($current->isPending() ? 'pending_exists' : 'approved_exists');
                }

                $proposal = new TopicProposal([
                    'title' => trim($data['title']),
                    'description' => self::blankToNull($data['description'] ?? null),
                ]);
                $proposal->organization_id = $locked->organization_id;
                $proposal->topic_activity_id = $locked->activity_id;
                $proposal->student_id = $holder['student_id'];
                $proposal->group_id = $holder['group_id'];
                $proposal->save();

                return $proposal;
            });
        } catch (UniqueConstraintViolationException) {
            self::refuse('pending_exists');
        }
    }

    /**
     * The proposals of a holder in the activity, newest first.
     *
     * @param  array{student_id: string|null, group_id: string|null}  $holder
     * @return Builder<TopicProposal>
     */
    public static function holderProposals(TopicActivity $topicActivity, array $holder): Builder
    {
        $query = TopicProposal::query()->where('topic_activity_id', $topicActivity->activity_id);

        $query = $holder['group_id'] !== null
            ? $query->where('group_id', $holder['group_id'])
            : $query->where('student_id', $holder['student_id']);

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Ids of the groups that take part in the activity: every group with a
     * proposal, whatever its status.
     *
     * @return list<string>
     */
    public static function participatingGroupIds(TopicActivity $topicActivity): array
    {
        return TopicProposal::query()
            ->where('topic_activity_id', $topicActivity->activity_id)
            ->whereNotNull('group_id')
            ->distinct()
            ->pluck('group_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * The activity's subtype row, created with the first proposal.
     */
    private static function subtypeRow(Activity $activity): TopicActivity
    {
        $topicActivity = TopicActivity::forActivityOrNew($activity);

        if (! $topicActivity->exists) {
            $topicActivity->save();
        }

        return $topicActivity->setRelation('activity', $activity);
    }

    /**
     * Who proposes: the student in an individual activity, else the group
     * the student acts for.
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

        if ($this->groups->overlaps($group, self::participatingGroupIds($topicActivity))) {
            self::refuse('member_in_other_group', 'group_id');
        }

        return ['student_id' => null, 'group_id' => $group->id];
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, int|string>  $replace
     *
     * @throws ValidationException
     */
    public static function refuse(string $reason, string $field = 'proposal', array $replace = []): never
    {
        throw ValidationException::withMessages([
            $field => (string) __("activities.proposals.propose.refused.{$reason}", $replace),
        ]);
    }
}

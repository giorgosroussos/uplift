<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\TopicProposalStatus;
use App\Modules\Activities\Models\TopicActivity;
use App\Modules\Activities\Models\TopicProposal;
use App\Modules\Activities\Support\ActingGroups;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * UC-015 and UC-027 step 1: a topic proposal activity as its viewer may
 * see it. A student sees only their own proposals, or in a group activity
 * those of the groups they are a current member of, with each status and
 * the teacher's comment (OWN-009); the owning teacher sees every proposal,
 * pending ones first, and decides them.
 */
class ShowTopicProposals
{
    public function __construct(private readonly ActingGroups $groups) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, TopicActivity $topicActivity): array
    {
        $activity = $topicActivity->activity;
        $gate = Gate::forUser($user);
        $canPropose = $gate->allows('propose', $topicActivity);
        $reviews = $gate->allows('viewProposals', $topicActivity);
        $isOpen = $topicActivity->isOpen();

        return [
            'topicProposal' => [
                'activity_id' => $activity->id,
                'title' => $activity->title,
                'description' => $activity->description,
                'status' => $activity->status->value,
                'course_space_title' => $activity->courseSpace->title,
                'deadline_at' => $activity->deadline_at?->toIso8601String(),
                'is_group' => $activity->is_group,
                'group_size' => $activity->group_size,
                'is_open' => $isOpen,
            ],
            'own' => $canPropose && ! $activity->is_group ? $this->own($user, $topicActivity) : null,
            'groups' => $canPropose && $activity->is_group ? $this->groupProposals($user, $topicActivity) : [],
            'proposals' => $reviews ? $this->all($topicActivity) : [],
            'can' => [
                'propose' => $canPropose && $isOpen,
                'decide' => $reviews,
                'update' => $gate->allows('update', $activity),
            ],
        ];
    }

    /**
     * @return array{proposals: list<array<string, mixed>>, may_propose: bool}
     */
    private function own(User $student, TopicActivity $topicActivity): array
    {
        $proposals = $this->holderProposals($topicActivity, ['student_id' => (string) $student->getKey(), 'group_id' => null]);

        return ['proposals' => $proposals, 'may_propose' => self::mayProposeAgain($proposals)];
    }

    /**
     * The student's current groups in the space with their proposals and
     * whether they may propose in this activity (SCOPE-025).
     *
     * @return list<array<string, mixed>>
     */
    private function groupProposals(User $student, TopicActivity $topicActivity): array
    {
        $activity = $topicActivity->activity;
        $participating = $topicActivity->exists ? ProposeTopic::participatingGroupIds($topicActivity) : [];

        return $this->groups->of($student, $activity)
            ->map(function (Group $group) use ($topicActivity, $activity, $participating): array {
                $proposals = $this->holderProposals($topicActivity, ['student_id' => null, 'group_id' => $group->id]);

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'members' => count($this->groups->memberIds($group)),
                    'too_large' => $this->groups->tooLarge($group, $activity),
                    'in_other_group' => $this->groups->overlaps($group, $participating),
                    'proposals' => $proposals,
                    'may_propose' => self::mayProposeAgain($proposals),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Every proposal of the activity for its teacher: pending ones first,
     * oldest first (the order to decide them in), then the decided ones,
     * newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function all(TopicActivity $topicActivity): array
    {
        if (! $topicActivity->exists) {
            return [];
        }

        $proposals = $topicActivity->proposals()->with(['student', 'group'])->get();
        [$pending, $decided] = $proposals->partition(fn (TopicProposal $proposal): bool => $proposal->isPending());

        return $pending->concat($decided->reverse())
            ->map(fn (TopicProposal $proposal): array => [
                ...self::row($proposal),
                'proposer' => $proposal->group_id !== null ? (string) $proposal->group?->name : (string) $proposal->student?->name,
                'is_group' => $proposal->group_id !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{student_id: string|null, group_id: string|null}  $holder
     * @return list<array<string, mixed>>
     */
    private function holderProposals(TopicActivity $topicActivity, array $holder): array
    {
        if (! $topicActivity->exists) {
            return [];
        }

        return ProposeTopic::holderProposals($topicActivity, $holder)->get()
            ->map(fn (TopicProposal $proposal): array => self::row($proposal))
            ->values()
            ->all();
    }

    /**
     * Whether none of the holder's proposals is pending or approved.
     *
     * @param  list<array<string, mixed>>  $proposals
     */
    private static function mayProposeAgain(array $proposals): bool
    {
        foreach ($proposals as $proposal) {
            if ($proposal['status'] !== TopicProposalStatus::Rejected->value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(TopicProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description' => $proposal->description,
            'status' => $proposal->status->value,
            'teacher_comment' => $proposal->teacher_comment,
            'created_at' => $proposal->created_at?->toIso8601String(),
            'decided_at' => $proposal->isPending() ? null : $proposal->updated_at?->toIso8601String(),
        ];
    }
}

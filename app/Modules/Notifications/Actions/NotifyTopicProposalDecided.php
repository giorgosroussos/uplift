<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Activities\Enums\TopicProposalStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Models\TopicProposal;
use App\Modules\Courses\Enums\GroupMembershipStatus;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OrganizationUrl;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\TopicProposalDecidedEmail;
use App\Modules\Notifications\Support\NotificationMailer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DEC-001 / UC-027: tells the proposer that the teacher approved or
 * rejected their topic proposal: the student of an individual proposal, or
 * every current member of the proposing group. One in-app
 * topic_proposal_decided entry each, with the decision and the space and
 * activity titles as they are now (ids and title snapshots only, never the
 * description or the teacher's comment), then a queued email to each of
 * them who has not turned notification emails off.
 *
 * The in-app entries are written first and synchronously, so they exist
 * even when queueing or sending the email fails; a failing email never
 * blocks the decision (INT-001). Runs as the proposal's tenant.
 */
class NotifyTopicProposalDecided
{
    public function __construct(private readonly NotificationMailer $mailer) {}

    public function handle(string $topicProposalId): void
    {
        $proposal = TopicProposal::query()->find($topicProposalId);

        if ($proposal === null || $proposal->status === TopicProposalStatus::Pending) {
            return;
        }

        $activity = Activity::query()->with('courseSpace.organization')->find($proposal->topic_activity_id);

        if ($activity === null) {
            return;
        }

        $recipients = $this->recipients($proposal);

        if ($recipients->isEmpty()) {
            return;
        }

        $space = $activity->courseSpace;
        $status = $proposal->status->value;

        $data = [
            'course_space_id' => $space->id,
            'activity_id' => $activity->id,
            'topic_proposal_id' => $proposal->id,
            'status' => $status,
            'course_space_title' => $space->title,
            'activity_title' => $activity->title,
        ];

        DB::transaction(function () use ($recipients, $data): void {
            foreach ($recipients as $recipient) {
                Notification::query()->create([
                    'user_id' => $recipient->id,
                    'kind' => NotificationKind::TopicProposalDecided,
                    'data' => $data,
                ]);
            }
        });

        $url = OrganizationUrl::for($space->organization)->route('activities.proposals.show', $activity);

        foreach ($recipients as $recipient) {
            $this->mailer->queue($recipient, new TopicProposalDecidedEmail(
                $recipient->id,
                $proposal->id,
                $status,
                $space->title,
                $activity->title,
                $url,
            ));
        }
    }

    /**
     * The student of an individual proposal, or the current members of the
     * proposing group.
     *
     * @return Collection<int, User>
     */
    private function recipients(TopicProposal $proposal): Collection
    {
        if ($proposal->student_id !== null) {
            return User::query()->whereKey($proposal->student_id)->get();
        }

        return User::query()
            ->whereIn('id', GroupMembership::query()
                ->where('group_id', $proposal->group_id)
                ->where('status', GroupMembershipStatus::Member->value)
                ->select('student_id'))
            ->get();
    }
}

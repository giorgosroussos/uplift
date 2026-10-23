<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\OrganizationUrl;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\GroupInvitationEmail;
use App\Modules\Notifications\Support\NotificationMailer;

/**
 * DEC-001 / SCOPE-025: tells a student they were invited to a group: one
 * in-app group_invitation entry with the space title and the group name as
 * they are now (ids and snapshots only, never who invited them), then a
 * queued email unless they turned notification emails off. An invitation
 * that is no longer pending (answered or withdrawn before this runs) or a
 * deleted group notifies nobody.
 *
 * The in-app entry is written first and synchronously, so it exists even
 * when queueing or sending the email fails; a failing email never blocks
 * the invitation (INT-001). Runs as the membership's tenant.
 */
class NotifyGroupInvitation
{
    public function __construct(private readonly NotificationMailer $mailer) {}

    public function handle(string $groupMembershipId): void
    {
        $invitation = GroupMembership::query()->find($groupMembershipId);

        if ($invitation === null || ! $invitation->isInvited()) {
            return;
        }

        $group = Group::query()->with('courseSpace.organization')->find($invitation->group_id);
        $student = User::query()->find($invitation->student_id);

        if ($group === null || $student === null) {
            return;
        }

        $space = $group->courseSpace;

        Notification::query()->create([
            'user_id' => $student->id,
            'kind' => NotificationKind::GroupInvitation,
            'data' => [
                'course_space_id' => $space->id,
                'group_id' => $group->id,
                'group_membership_id' => $invitation->id,
                'course_space_title' => $space->title,
                'group_name' => $group->name,
            ],
        ]);

        $url = OrganizationUrl::for($space->organization)->route('courses.groups.show', [$space, $group]);

        $this->mailer->queue($student, new GroupInvitationEmail(
            $student->id,
            $invitation->id,
            $space->title,
            $group->name,
            $url,
        ));
    }
}

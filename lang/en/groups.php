<?php

/*
| Groups of a course space (UC-019, SCOPE-025): UI strings of the Vue pages
| and server-side messages. Every key here must also exist in
| lang/el/groups.php.
*/

return [

    'nav' => [
        'groups' => 'Groups',
    ],

    'index' => [
        'title' => 'Groups',
        'heading' => 'Groups of :space',
        'back' => 'Back to the course space',
        'read_only' => 'You see the groups of this course space with their members and pending invitations. Only students change their groups.',
        'empty_student' => 'You are not in any group of this course space yet. Create one and invite your classmates.',
        'empty' => 'There are no groups in this course space yet.',
        'create' => 'New group',
        'name' => 'Group name',
        'submit' => 'Create group',
        'open' => 'Open',
        'invited_you' => 'You are invited to this group.',
    ],

    'show' => [
        'back' => 'All groups',
        'owner' => 'Owner',
        'members' => 'Members',
        'joined_at' => 'Joined on',
        'invitations' => 'Pending invitations',
        'no_invitations' => 'There are no pending invitations.',
        'invite' => 'Invite',
        'invite_title' => 'Invite a student',
        'invite_text' => 'Students enrolled in this course space who are not members of and not invited to this group.',
        'choose_student' => 'Choose a student',
        'no_invitable' => 'Every enrolled student is already a member or invited.',
        'withdraw' => 'Withdraw',
        'accept' => 'Accept',
        'decline' => 'Decline',
        'rename' => 'Rename',
        'save' => 'Save',
        'transfer' => 'Transfer ownership',
        'transfer_title' => 'Transfer ownership',
        'transfer_text' => 'The member you choose becomes the owner of the group. You stay a member.',
        'choose_member' => 'Choose a member',
        'leave' => 'Leave group',
        'confirm_leave_title' => 'Leave this group?',
        'confirm_leave_text' => 'You are no longer a member. If you are the last member, the group is deleted.',
        'owner_leave_hint' => 'As the owner you can leave only after transferring ownership to another member.',
        'dissolve' => 'Dissolve group',
        'confirm_dissolve_title' => 'Dissolve this group?',
        'confirm_dissolve_text' => 'The group is deleted for all its members and its pending invitations are withdrawn. Work it has already submitted is kept.',
        'cancel' => 'Cancel',
        'you' => 'you',
    ],

    'attributes' => [
        'name' => 'group name',
        'student' => 'student',
    ],

    'errors' => [
        'not_enrolled' => 'Only students enrolled in this course space can be in its groups.',
        'already_invited_or_member' => 'This student is already a member of or invited to the group.',
        'not_a_member' => 'Choose a current member of the group.',
        'owner_must_transfer' => 'Transfer ownership to another member before you leave the group.',
        'invitation_closed' => 'This invitation is no longer pending.',
        'group_gone' => 'This group no longer exists.',
    ],

    'flash' => [
        'created' => 'Group created.',
        'renamed' => 'Group renamed.',
        'dissolved' => 'Group dissolved.',
        'invited' => 'Invitation sent.',
        'accepted' => 'You joined the group.',
        'declined' => 'Invitation declined.',
        'withdrawn' => 'Invitation withdrawn.',
        'left' => 'You left the group.',
        'transferred' => 'Ownership transferred.',
    ],

];

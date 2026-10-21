<?php

/*
| User management of the org admin (UC-032): UI strings and server-side
| messages. Every key here must also exist in lang/el/users.php.
*/

return [

    'nav' => 'Users',

    'roles' => [
        'student' => 'Student',
        'teacher' => 'Teacher',
        'org_admin' => 'Organisation admin',
    ],

    'statuses' => [
        'active' => 'Active',
        'inactive' => 'Deactivated',
    ],

    'attributes' => [
        'email' => 'email address',
        'role' => 'role',
    ],

    'index' => [
        'title' => 'Users',
        'description' => 'Invite people to your organisation, change their roles and deactivate accounts.',
        'search' => 'Search by name, nickname or email',
        'filter_role' => 'Role',
        'filter_status' => 'Status',
        'all_roles' => 'All roles',
        'all_statuses' => 'All statuses',
        'apply' => 'Filter',
        'empty' => 'No users match these filters.',
        'name' => 'Name',
        'email' => 'Email',
        'role' => 'Role',
        'status' => 'Status',
        'actions' => 'Actions',
        'you' => 'you',
        'change_role' => 'Change role',
        'deactivate' => 'Deactivate',
        'reactivate' => 'Reactivate',
        'previous' => 'Previous',
        'next' => 'Next',
        'page' => 'Page :current of :last',
    ],

    'invite' => [
        'title' => 'Invite a user',
        'email' => 'Email address',
        'role' => 'Role',
        'submit' => 'Send invitation',
    ],

    'invitations' => [
        'title' => 'Pending invitations',
        'empty' => 'There are no pending invitations.',
        'expires' => 'Expires',
        'expired' => 'Link expired',
        'revoke' => 'Revoke',
        'resend' => 'Resend',
        'email_failed' => 'Email not delivered',
    ],

    'errors' => [
        'email_taken' => 'This email address already belongs to a user of the platform.',
        'already_invited' => 'This email address already has a pending invitation.',
        'last_admin' => 'The organisation needs at least one active organisation admin.',
        'not_pending' => 'This invitation is no longer pending.',
        'not_resendable' => 'Only pending or expired invitations can be resent.',
    ],

    'flash' => [
        'invited' => 'Invitation sent.',
        'revoked' => 'Invitation revoked.',
        'resent' => 'Invitation sent again.',
        'role_changed' => 'Role changed.',
        'deactivated' => 'User deactivated.',
        'reactivated' => 'User reactivated.',
    ],

];

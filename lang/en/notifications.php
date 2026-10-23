<?php

/*
| Emails sent to users (queued notifications) and the notification centre. Every key here must also exist
| in lang/el/notifications.php.
*/

return [

    'greeting' => 'Hello!',
    'salutation' => 'Regards, :app',

    'reset_password' => [
        'subject' => 'Reset password',
        'intro' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Reset password',
        'expires' => 'This password reset link will expire in :count minutes.',
        'outro' => 'If you did not request a password reset, no further action is required.',
    ],

    'verify_email' => [
        'subject' => 'Verify email address',
        'intro' => 'Please click the button below to verify your email address.',
        'action' => 'Verify email address',
        'outro' => 'If you did not create an account, no further action is required.',
    ],

    'invitation' => [
        'subject' => 'Invitation to :organization',
        'intro' => 'You have been invited to join :organization as :role.',
        'action' => 'Accept invitation',
        'expires' => 'This link is valid for :count days and can be used once.',
        'outro' => 'If you did not expect this invitation, no further action is required.',
    ],

    'announcement_published' => [
        'subject' => 'New announcement in :space',
        'intro' => 'A new announcement was posted in :space.',
        'title' => 'Title: :title',
        'action' => 'Open the course space',
        'outro' => 'You can turn notification emails off in your notification settings.',
    ],

    'activity_published' => [
        'subject' => 'New activity in :space',
        'intro' => 'A new activity was published in :space.',
        'title' => 'Title: :title',
        'action' => 'Open the activity',
    ],

    'grade_finalised' => [
        'subject' => 'Your attempt at :title has been graded',
        'intro' => 'Your attempt at :title in :space has been graded.',
        'action' => 'View your result',
    ],

    'grade_changed' => [
        'subject' => 'Your grade for :title has changed',
        'intro' => 'Your grade for :title in :space has changed after a regrade.',
        'action' => 'View your result',
    ],

    'topic_proposal_decided' => [
        'approved' => [
            'subject' => 'Your topic proposal for :title was approved',
            'intro' => 'Your topic proposal for :title in :space was approved.',
        ],
        'rejected' => [
            'subject' => 'Your topic proposal for :title was rejected',
            'intro' => 'Your topic proposal for :title in :space was rejected. The teacher\'s comment is on the proposals page.',
        ],
        'action' => 'View your proposal',
    ],

    'group_invitation' => [
        'subject' => 'Invitation to the group :group in :space',
        'intro' => 'You have been invited to join the group :group in :space.',
        'action' => 'View the invitation',
    ],

    'outro' => 'You can turn notification emails off in your notification settings.',

    /*
    | Notification centre, header bell and notification settings (UC-007,
    | DEC-002), used by the Vue frontend.
    */

    'centre' => [
        'title' => 'Notifications',
        'heading' => 'Notifications',
        'empty' => 'You have no notifications.',
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'mark_read' => 'Mark as read',
        'mark_unread' => 'Mark as unread',
        'mark_all_read' => 'Mark all as read',
        'unread' => 'Unread',
        'previous' => 'Previous',
        'next' => 'Next',
        'settings' => 'Notification settings',
        'kinds' => [
            'announcement_published' => 'New announcement in :space: :title',
            'activity_published' => 'New activity in :space: :title',
            'grade_finalised' => 'Your attempt at :title in :space has been graded',
            'grade_changed' => 'Your grade for :title in :space has changed',
            'topic_proposal_decided' => [
                'approved' => 'Your topic proposal for :title in :space was approved',
                'rejected' => 'Your topic proposal for :title in :space was rejected',
            ],
            'group_invitation' => 'You have been invited to the group :group in :space',
        ],
    ],

    'bell' => [
        'label' => 'Notifications',
        'unread' => ':count unread',
    ],

    'settings' => [
        'title' => 'Notification settings',
        'heading' => 'Notification settings',
        'email_label' => 'Send me notification emails',
        'email_help' => 'Emails about new announcements and other course updates. Account emails, such as password resets, are always sent, and notifications always appear in the notification centre.',
        'save' => 'Save',
        'saved' => 'Notification settings saved.',
        'nav' => 'Notification settings',
    ],

];

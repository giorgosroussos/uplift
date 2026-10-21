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

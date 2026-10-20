<?php

/*
| Emails sent to users (queued notifications). Every key here must also exist
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

];

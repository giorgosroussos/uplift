<?php

/*
| Platform administration on the apex domain (SEC-006): login of platform
| admins with required TOTP MFA (SEC-011). UI strings and server-side
| messages. Every key here must also exist in lang/el/platform.php.
*/

return [

    'attributes' => [
        'email' => 'email address',
        'password' => 'password',
        'code' => 'authentication code',
        'recovery_code' => 'recovery code',
    ],

    'login' => [
        'page_title' => 'Platform administration',
        'title' => 'Platform administration',
        'description' => 'Log in with your platform admin account',
        'submit' => 'Log in',
    ],

    'challenge' => [
        'title' => 'Two-factor authentication',
        'description' => 'Enter the 6-digit code from your authenticator app.',
        'recovery_description' => 'Enter one of your recovery codes.',
        'code' => 'Authentication code',
        'recovery_code' => 'Recovery code',
        'use_recovery' => 'Use a recovery code',
        'use_code' => 'Use an authentication code',
        'submit' => 'Continue',
    ],

    'setup' => [
        'title' => 'Set up two-factor authentication',
        'description' => 'Two-factor authentication is required for platform admins.',
        'scan' => 'Scan the QR code with your authenticator app, or enter the setup key, then enter the 6-digit code it shows.',
        'qr_code' => 'QR code for your authenticator app',
        'key' => 'Setup key',
        'code' => 'Authentication code',
        'submit' => 'Confirm and continue',
    ],

    'recovery' => [
        'title' => 'Your recovery codes',
        'description' => 'Store these codes somewhere safe. Each one lets you log in once if you lose your authenticator. They are shown only now.',
        'continue' => 'Continue',
    ],

    'dashboard' => [
        'title' => 'Platform administration',
        'description' => 'You are logged in as a platform admin.',
        'signed_in_as' => 'Signed in as :name',
        'log_out' => 'Log out',
    ],

    'two_factor' => [
        'invalid_code' => 'The authentication code is not valid.',
        'invalid_recovery_code' => 'The recovery code is not valid.',
    ],

];

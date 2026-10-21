<?php

/*
| Platform administration on the apex domain (SEC-006): login of platform
| admins with required TOTP MFA (SEC-011), the platform dashboard (UC-035)
| and organisation management (UC-036). UI strings and server-side
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

    'nav' => [
        'dashboard' => 'Dashboard',
        'organizations' => 'Organisations',
    ],

    'dashboard' => [
        'title' => 'Platform administration',
        'description' => 'Key figures across all organisations.',
        'signed_in_as' => 'Signed in as :name',
        'log_out' => 'Log out',
        'organizations' => 'Organisations',
        'users' => 'Users',
        'total' => 'Total',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'active_users' => 'Active users',
        'recent' => 'Newest organisations',
        'recent_empty' => 'There are no organisations yet.',
        'manage' => 'Manage organisations',
    ],

    'roles' => [
        'student' => 'Students',
        'teacher' => 'Teachers',
        'org_admin' => 'Organisation admins',
    ],

    'statuses' => [
        'active' => 'Active',
        'suspended' => 'Suspended',
    ],

    'organizations' => [
        'title' => 'Organisations',
        'description' => 'All organisations of the platform: see their status, suspend or reactivate them.',
        'search' => 'Search by name or subdomain',
        'all_statuses' => 'All statuses',
        'filter' => 'Filter',
        'empty' => 'No organisations match.',
        'name' => 'Name',
        'subdomain' => 'Subdomain',
        'status' => 'Status',
        'users' => 'Users',
        'created' => 'Created',
        'suspend' => 'Suspend',
        'reactivate' => 'Reactivate',
        'confirm_suspend_title' => 'Suspend :name?',
        'confirm_suspend_body' => 'Its users will not be able to log in until the organisation is reactivated.',
        'confirm_reactivate_title' => 'Reactivate :name?',
        'confirm_reactivate_body' => 'Its users will be able to log in again.',
        'cancel' => 'Cancel',
        'page' => 'Page :current of :last',
        'previous' => 'Previous',
        'next' => 'Next',
        'flash' => [
            'suspended' => 'The organisation was suspended.',
            'reactivated' => 'The organisation was reactivated.',
        ],
        'errors' => [
            'not_allowed' => [
                'active' => 'The organisation is already active.',
                'suspended' => 'The organisation is already suspended.',
            ],
        ],
    ],

    'two_factor' => [
        'invalid_code' => 'The authentication code is not valid.',
        'invalid_recovery_code' => 'The recovery code is not valid.',
    ],

];

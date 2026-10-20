<?php

/*
| The user's own settings (UC-006): profile, password and MFA of org admins.
| UI strings and server-side messages. Every key here must also exist in
| lang/el/profile.php.
*/

return [

    'nav' => 'Settings',

    'settings' => [
        'title' => 'Settings',
        'description' => 'Manage your profile and account security.',
        'sections' => 'Settings sections',
        'profile' => 'Profile',
        'password' => 'Password',
        'two_factor' => 'Two-factor authentication',
    ],

    'attributes' => [
        'name' => 'name',
        'nickname' => 'nickname',
        'avatar' => 'avatar',
        'locale' => 'language',
        'current_password' => 'current password',
        'password' => 'new password',
    ],

    'validation' => [
        'nickname_format' => 'The nickname may contain only letters, digits and the characters _ - .',
        'nickname_length' => 'The nickname must be between :min and :max characters.',
        'nickname_taken' => 'This nickname is already used in your organisation.',
    ],

    'flash' => [
        'saved' => 'Your profile has been saved.',
        'password_changed' => 'Your password has been changed. Your other sessions have been logged out.',
    ],

    'profile' => [
        'title' => 'Profile',
        'description' => 'Your name, nickname, avatar and language.',
        'name' => 'Name',
        'nickname' => 'Nickname',
        'nickname_help' => 'Shown instead of your name on leaderboards and in live quizzes. 3 to 30 characters: letters, digits and _ - .',
        'avatar' => 'Avatar',
        'avatar_help' => 'Shown with your nickname on leaderboards and in live quizzes.',
        'avatar_none' => 'Initials',
        'avatar_option' => 'Avatar :number',
        'language' => 'Language',
        'language_default' => 'Organisation default (:language)',
        'save' => 'Save',
        'account' => 'Account details',
        'account_help' => 'These details are managed by your organisation and cannot be changed here.',
        'email' => 'Email address',
        'role' => 'Role',
        'date_of_birth' => 'Date of birth',
        'is_minor' => 'Minor',
        'yes' => 'Yes',
        'no' => 'No',
        'not_set' => 'Not set',
    ],

    'password' => [
        'title' => 'Change password',
        'description' => 'Enter your current password and a new one. Your other sessions will be logged out.',
        'current' => 'Current password',
        'new' => 'New password',
        'confirm' => 'Confirm new password',
        'save' => 'Change password',
    ],

    'two_factor' => [
        'title' => 'Two-factor authentication',
        'description' => 'Protect your administrator account with a code from an authenticator app when you log in.',
        'status_enabled' => 'Two-factor authentication is on.',
        'status_disabled' => 'Two-factor authentication is off.',
        'enable' => 'Turn on',
        'disable' => 'Turn off',
        'cancel' => 'Cancel',
        'setup_title' => 'Finish turning on two-factor authentication',
        'setup_scan' => 'Scan this QR code with your authenticator app, or enter the setup key, then enter the 6-digit code it shows.',
        'setup_key' => 'Setup key',
        'qr_code' => 'QR code for your authenticator app',
        'code' => 'Authentication code',
        'confirm' => 'Confirm',
        'recovery_title' => 'Recovery codes',
        'recovery_help' => 'Store these codes somewhere safe. Each one lets you log in once if you lose your authenticator app.',
        'recovery_show' => 'Show recovery codes',
        'recovery_hide' => 'Hide recovery codes',
        'recovery_regenerate' => 'Generate new recovery codes',
        'invalid_code' => 'The authentication code is not valid.',
        'invalid_recovery_code' => 'The recovery code is not valid.',
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

];

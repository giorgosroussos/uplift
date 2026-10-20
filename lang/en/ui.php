<?php

/*
| UI strings of the Vue frontend (laravel-vue-i18n). Every key here must also
| exist in lang/el/ui.php; tests/Feature/TranslationsTest.php enforces it.
*/

return [

    'common' => [
        'breadcrumb' => 'Breadcrumb',
        'close' => 'Close',
        'loading' => 'Loading',
        'more' => 'More',
    ],

    'nav' => [
        'platform' => 'Platform',
        'dashboard' => 'Dashboard',
        'log_in' => 'Log in',
        'log_out' => 'Log out',
        'main' => 'Main navigation',
    ],

    'sidebar' => [
        'title' => 'Sidebar',
        'description' => 'Displays the mobile sidebar.',
        'toggle' => 'Toggle sidebar',
    ],

    'welcome' => [
        'title' => 'Welcome',
        'heading' => 'Course spaces with interactive activities',
        'lead' => 'Quizzes, polls, topic selection and slot booking for your organisation, with a real-time live quiz.',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'student' => 'Student dashboard',
        'teacher' => 'Teacher dashboard',
        'org_admin' => 'Organisation admin dashboard',
    ],

    'auth' => [
        'email' => 'Email address',
        'email_placeholder' => 'email@example.com',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'show_password' => 'Show password',
        'hide_password' => 'Hide password',

        'login' => [
            'title' => 'Log in to your account',
            'description' => 'Enter your email and password below to log in',
            'page_title' => 'Log in',
            'forgot_password' => 'Forgot your password?',
            'remember_me' => 'Remember me',
            'submit' => 'Log in',
            'providers_unavailable' => 'Single sign-on is unavailable right now. You can still log in with your email and password.',
        ],

        'forgot_password' => [
            'title' => 'Forgot password',
            'description' => 'Enter your email to receive a password reset link',
            'submit' => 'Email password reset link',
            'return_to' => 'Or, return to',
            'log_in' => 'log in',
        ],

        'reset_password' => [
            'title' => 'Reset password',
            'description' => 'Please enter your new password below',
            'submit' => 'Reset password',
        ],

        'verify_email' => [
            'title' => 'Email verification',
            'description' => 'Please verify your email address by clicking on the link we just emailed to you.',
            'link_sent' => 'A new verification link has been sent to your email address.',
            'resend' => 'Resend verification email',
        ],

        'confirm_password' => [
            'title' => 'Confirm password',
            'description' => 'This is a secure area of the application. Please confirm your password before continuing.',
            'submit' => 'Confirm password',
        ],
    ],

];

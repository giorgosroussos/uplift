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
        'register' => 'Register your organisation',
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
            'verification_sent' => 'Your organisation has been registered. We sent a verification link to your email address; log in to continue.',
        ],

        'register' => [
            'title' => 'Register your organisation',
            'description' => 'Create your organisation and its first administrator account',
            'page_title' => 'Register',
            'organization_section' => 'Organisation',
            'admin_section' => 'Administrator',
            'organization_name' => 'Organisation name',
            'organization_name_placeholder' => 'Acme School',
            'subdomain' => 'Subdomain',
            'subdomain_placeholder' => 'acme',
            'subdomain_hint' => '3 to 30 characters: lowercase letters, digits and hyphens.',
            'subdomain_checking' => 'Checking availability…',
            'subdomain_available' => 'This subdomain is available.',
            'subdomain_check_failed' => 'The availability could not be checked right now.',
            'admin_name' => 'Your name',
            'admin_name_placeholder' => 'Full name',
            'submit' => 'Register organisation',
            'too_many' => 'Too many organisations were registered from your network. Please try again later.',
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

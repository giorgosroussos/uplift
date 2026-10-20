<?php

/*
| Accepting an invitation (UC-005): UI strings and server-side messages.
| Every key here must also exist in lang/el/invitations.php.
*/

return [

    'attributes' => [
        'name' => 'name',
        'password' => 'password',
    ],

    'accept' => [
        'title' => 'Join :organization',
        'description' => 'You were invited as :role. Set your name and a password to activate your account.',
        'page_title' => 'Accept invitation',
        'name' => 'Your name',
        'name_placeholder' => 'Full name',
        'submit' => 'Activate account',
    ],

    'unavailable' => [
        'title' => 'Invitation unavailable',
        'expired' => 'This invitation has expired.',
        'accepted' => 'This invitation has already been accepted.',
        'revoked' => 'This invitation has been revoked.',
        'contact_admin' => 'Please contact your organisation\'s admin for a new invitation.',
        'log_in' => 'Log in',
    ],

    'email_taken' => 'This email address already belongs to a user of the platform. Please contact your organisation\'s admin.',

];

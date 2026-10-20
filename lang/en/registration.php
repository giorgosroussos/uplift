<?php

/*
| Server-side messages of the organisation sign-up (UC-001). Every key here
| must also exist in lang/el/registration.php.
*/

return [

    'attributes' => [
        'organization_name' => 'organisation name',
        'subdomain' => 'subdomain',
        'name' => 'name',
        'email' => 'email address',
        'password' => 'password',
    ],

    'subdomain' => [
        'invalid' => 'Use 3 to 30 characters: lowercase letters, digits and hyphens, not at the start or end.',
        'reserved' => 'This subdomain is reserved by the platform.',
        'taken' => 'This subdomain is already taken.',
        'unavailable' => 'Subdomain unavailable',
        'too_many_title' => 'Too many requests',
        'too_many' => 'Too many availability checks. Please wait a minute and try again.',
    ],

    'too_many' => 'Too many organisations were registered from your network. Please try again later.',

];

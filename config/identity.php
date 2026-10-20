<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identity providers (EXT-001, deferred)
    |--------------------------------------------------------------------------
    |
    | Every call to a single sign-on identity provider goes through
    | App\Modules\Identity\Contracts\IdentityProviderResolver with this
    | timeout and no retries (interactive login).
    |
    */

    'providers' => [
        'timeout' => (int) env('IDENTITY_PROVIDER_TIMEOUT', 10),
    ],

];

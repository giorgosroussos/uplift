<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apex domain
    |--------------------------------------------------------------------------
    |
    | Organisations live on <subdomain>.<domain>; the apex domain itself hosts
    | the public site, sign-up and the platform admin. Defaults to the host
    | of APP_URL.
    |
    */

    'domain' => env('TENANCY_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

];

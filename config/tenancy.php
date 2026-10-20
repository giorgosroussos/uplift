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

    /*
    |--------------------------------------------------------------------------
    | Logo disk
    |--------------------------------------------------------------------------
    |
    | Private Storage disk of the organisation logos (DATA-011, SEC-010). The
    | logos are served through a tenant route, never from the public
    | directory; an S3-compatible disk can replace the local one (OPS-001).
    |
    */

    'logo_disk' => env('TENANCY_LOGO_DISK', 'local'),

];

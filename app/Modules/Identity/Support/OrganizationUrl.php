<?php

namespace App\Modules\Identity\Support;

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Config;

/**
 * Builds absolute URLs on an organisation's subdomain, independent of the
 * current request: links in queued mails must point at the organisation
 * even when they are built on the apex domain or in a worker.
 */
final class OrganizationUrl
{
    public static function for(Organization $organization): UrlGenerator
    {
        /** @var UrlGenerator $generator */
        $generator = clone app('url');
        $generator->forceRootUrl(self::root($organization));

        return $generator;
    }

    /**
     * <scheme>://<subdomain>.<tenancy.domain>[:port], scheme and port taken
     * from APP_URL.
     */
    public static function root(Organization $organization): string
    {
        $appUrl = (string) Config::get('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($appUrl, PHP_URL_PORT);

        return $scheme.'://'.$organization->subdomain.'.'.Config::get('tenancy.domain').($port ? ':'.$port : '');
    }
}

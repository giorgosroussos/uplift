<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Support\OrganizationLogo;
use App\Modules\Tenancy\Support\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the current organisation's logo from the private disk (DATA-011).
 * Public on the organisation's subdomain: the login page shows it to guests.
 */
class OrganizationLogoController extends Controller
{
    public function __invoke(TenantContext $tenant, OrganizationLogo $logo): StreamedResponse
    {
        $path = $tenant->current()?->logo_path;
        $disk = $logo->disk();

        abort_if($path === null || ! $disk->exists($path), 404);

        $headers = [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            // Defence in depth for svg: even if opened directly, nothing runs.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ];

        $mime = match (pathinfo($path, PATHINFO_EXTENSION)) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            default => 'image/jpeg',
        };

        return response()->stream(function () use ($disk, $path): void {
            echo $disk->get($path);
        }, 200, [...$headers, 'Content-Type' => $mime]);
    }
}

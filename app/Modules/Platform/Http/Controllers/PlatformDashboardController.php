<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\GetPlatformMetrics;
use App\Modules\Platform\Support\PlatformSession;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-035: the platform dashboard of a logged-in platform admin, with the key
 * platform metrics across all organisations.
 */
class PlatformDashboardController extends Controller
{
    public function __invoke(GetPlatformMetrics $metrics): Response
    {
        return Inertia::render('Platform/Dashboard', [
            'admin' => ['name' => PlatformSession::admin()?->name],
            'metrics' => $metrics->handle(),
        ]);
    }
}

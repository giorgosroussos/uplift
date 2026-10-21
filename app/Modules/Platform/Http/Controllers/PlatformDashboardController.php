<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Support\PlatformSession;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page of a logged-in platform admin. The dashboard content and
 * organisation management (UC-035, UC-036) come with P8-T2.
 */
class PlatformDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Platform/Dashboard', [
            'admin' => ['name' => PlatformSession::admin()?->name],
        ]);
    }
}

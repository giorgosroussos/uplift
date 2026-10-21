<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\ListOrganizations;
use App\Modules\Platform\Actions\ReactivateOrganization;
use App\Modules\Platform\Actions\SuspendOrganization;
use App\Modules\Platform\Http\Requests\ListOrganizationsRequest;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Platform\Support\PlatformSession;
use App\Modules\Tenancy\Enums\OrganizationStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-036: platform admins list organisations, see their status and suspend
 * or reactivate them (SEC-006: platform guard on the apex domain only).
 */
class OrganizationController extends Controller
{
    public function index(ListOrganizationsRequest $request, ListOrganizations $listOrganizations): Response
    {
        $filters = $request->filters();

        return Inertia::render('Platform/Organizations', [
            'organizations' => $listOrganizations->handle($filters),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'statuses' => array_map(fn (OrganizationStatus $status): string => $status->value, OrganizationStatus::cases()),
        ]);
    }

    public function suspend(Organization $organization, SuspendOrganization $suspendOrganization): RedirectResponse
    {
        $suspendOrganization->handle($this->admin(), $organization);

        return $this->done('platform.organizations.flash.suspended');
    }

    public function reactivate(Organization $organization, ReactivateOrganization $reactivateOrganization): RedirectResponse
    {
        $reactivateOrganization->handle($this->admin(), $organization);

        return $this->done('platform.organizations.flash.reactivated');
    }

    private function admin(): PlatformAdmin
    {
        $admin = PlatformSession::admin();
        abort_if($admin === null, 403);

        return $admin;
    }

    private function done(string $messageKey): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => (string) __($messageKey)]);

        return back();
    }
}

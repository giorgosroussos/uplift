<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\UpdateOrganizationSettings;
use App\Modules\Tenancy\Enums\GradingScale;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Http\Requests\UpdateOrganizationSettingsRequest;
use App\Modules\Tenancy\Support\OrganizationLogo;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UC-033: the org admin's organisation settings (ARCH-008: authorised by
 * OrganizationPolicy through the OrganizationMember contract).
 */
class OrganizationSettingsController extends Controller
{
    public function edit(TenantContext $tenant): Response
    {
        $organization = $tenant->current();
        abort_if($organization === null, 404);

        Gate::authorize('update', $organization);

        return Inertia::render('Tenancy/OrganizationSettings', [
            'settings' => [
                'subdomain' => $organization->subdomain,
                'logo_url' => OrganizationLogo::url($organization),
                'accent_color' => $organization->accent_color,
                'student_term_el' => $organization->student_term_el,
                'student_term_en' => $organization->student_term_en,
                'teacher_term_el' => $organization->teacher_term_el,
                'teacher_term_en' => $organization->teacher_term_en,
                'grading_scale' => $organization->grading_scale->value,
                'leaderboard_enabled' => $organization->leaderboard_enabled,
                'default_locale' => $organization->default_locale->value,
            ],
            'gradingScales' => array_map(fn (GradingScale $scale): string => $scale->value, GradingScale::cases()),
            'locales' => array_map(fn (Locale $locale): string => $locale->value, Locale::cases()),
        ]);
    }

    public function update(UpdateOrganizationSettingsRequest $request, UpdateOrganizationSettings $updateSettings): RedirectResponse
    {
        $updateSettings->handle(
            $request->organization(),
            (string) $request->user()?->getAuthIdentifier(),
            $request->settings(),
            $request->logo(),
            $request->removeLogo(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => (string) __('organization.flash.saved')]);

        return back();
    }
}

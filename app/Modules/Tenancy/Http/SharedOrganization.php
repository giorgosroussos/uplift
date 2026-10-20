<?php

namespace App\Modules\Tenancy\Http;

use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Support\OrganizationLogo;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\App;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

/**
 * The Inertia shared prop `organization` (DATA-011): the current
 * organisation's name, logo URL, accent colour, terms in the current locale,
 * leaderboard switch and grading scale; null outside an organisation.
 *
 * The fields are shared in dot notation, so a page that passes its own
 * `organization` prop (the login page passes the name) is completed with
 * them instead of hiding them.
 */
final class SharedOrganization implements ProvidesInertiaProperties
{
    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        $organization = app(TenantContext::class)->current();

        if ($organization === null) {
            return ['organization' => null];
        }

        $greek = App::getLocale() === Locale::Greek->value;

        return [
            'organization.name' => $organization->name,
            'organization.logo_url' => OrganizationLogo::url($organization),
            'organization.accent_color' => $organization->accent_color,
            'organization.student_term' => $greek ? $organization->student_term_el : $organization->student_term_en,
            'organization.teacher_term' => $greek ? $organization->teacher_term_el : $organization->teacher_term_en,
            'organization.leaderboard_enabled' => $organization->leaderboard_enabled,
            'organization.grading_scale' => $organization->grading_scale->value,
        ];
    }
}

<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\RegisterOrganization;
use App\Modules\Identity\Http\Requests\RegisterOrganizationRequest;
use App\Modules\Identity\Support\OrganizationUrl;
use App\Modules\Identity\Support\RegistrationRateLimiter;
use App\Modules\Tenancy\Enums\Locale;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * UC-001: public sign-up on the apex domain. Session cookies are host-only
 * (SEC-010), so the new admin is not logged in here: the visitor lands on
 * the login page of the organisation's subdomain.
 */
class RegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Register', [
            'domain' => (string) config('tenancy.domain'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(RegisterOrganizationRequest $request, RegisterOrganization $registerOrganization, RegistrationRateLimiter $limiter): HttpResponse
    {
        $admin = $registerOrganization->handle(
            $request->registration(),
            Locale::tryFrom(App::getLocale()) ?? Locale::English,
        );

        $limiter->recordRegistration($request);

        return Inertia::location(
            OrganizationUrl::for($admin->organization)->route('login', ['verification' => 'sent']),
        );
    }
}

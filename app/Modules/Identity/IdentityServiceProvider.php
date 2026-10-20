<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Actions\AuthenticateUser;
use App\Modules\Identity\Actions\ResetUserPassword;
use App\Modules\Identity\Contracts\IdentityProviderResolver;
use App\Modules\Identity\Http\LoginPage;
use App\Modules\Identity\Http\Middleware\EnsureAccountIsActive;
use App\Modules\Identity\Http\Responses\PasswordResetFailedResponse;
use App\Modules\Identity\Http\Responses\PasswordResetLinkRequestedResponse;
use App\Modules\Identity\Http\Responses\ProblemDetails;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\UserPolicy;
use App\Modules\Identity\Support\NoIdentityProviders;
use App\Modules\Identity\Support\OfflineUncompromisedVerifier;
use App\Modules\Identity\Support\TenantLoginRateLimiter;
use App\Modules\Identity\Support\TenantPasswordBrokerManager;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Users and login per organisation subdomain (UC-003, UC-004), built on
 * Fortify: the routes are Fortify's (config/fortify.php limits them to
 * organisation subdomains), the behaviour is configured here.
 */
class IdentityServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // The framework registers the password broker manager in a deferred
        // provider; extending survives that registration.
        $this->app->extend('auth.password', fn ($manager, $app) => $manager instanceof TenantPasswordBrokerManager
            ? $manager
            : new TenantPasswordBrokerManager($app));

        $this->app->bind(LoginRateLimiter::class, TenantLoginRateLimiter::class);
        $this->app->bind(IdentityProviderResolver::class, NoIdentityProviders::class);

        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestedResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestedResponse::class);
        $this->app->bind(FailedPasswordResetResponse::class, PasswordResetFailedResponse::class);

        if (! $this->app->isProduction()) {
            $this->app->singleton(UncompromisedVerifier::class, OfflineUncompromisedVerifier::class);
        }
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(User::class, UserPolicy::class);

        // SEC-010: at least 8 characters, not known to be compromised.
        Password::defaults(fn (): Password => Password::min(8)->uncompromised());

        $this->configureFortify();
        $this->configureMiddleware();
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // SEC-010: the live subdomain check of the sign-up page, 60 per
        // minute per IP; the JSON endpoint answers in RFC 9457 form.
        RateLimiter::for('subdomain-check', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('subdomain-check:'.$request->ip())
            ->response(fn (Request $request, array $headers) => ProblemDetails::response(
                429,
                'urn:uplift:problem:too-many-requests',
                (string) __('registration.subdomain.too_many_title'),
                (string) __('registration.subdomain.too_many'),
                headers: $headers,
            )));
    }

    private function configureFortify(): void
    {
        Fortify::authenticateUsing(fn (Request $request): ?User => $this->app->make(AuthenticateUser::class)->handle(
            (string) $request->input(Fortify::username()),
            (string) $request->input('password'),
        ));

        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn (Request $request) => $this->app->make(LoginPage::class)($request));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->input('email'),
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    private function configureMiddleware(): void
    {
        /** @var Kernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);

        // A password change ends the user's other sessions (SEC-010), and a
        // deactivated user or suspended organisation ends the session.
        $kernel->appendMiddlewareToGroup('web', AuthenticateSession::class);
        $kernel->appendMiddlewareToGroup('web', EnsureAccountIsActive::class);
    }
}

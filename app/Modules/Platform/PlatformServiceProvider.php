<?php

namespace App\Modules\Platform;

use App\Modules\Platform\Console\CreatePlatformAdminCommand;
use App\Modules\Platform\Models\PlatformAdmin;
use App\Modules\Platform\Support\PlatformSession;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Platform admins (SEC-006): their own table, their own session guard
 * (`platform`) on the apex domain, and required TOTP MFA (SEC-011).
 */
class PlatformServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // The guard and its user provider belong to this module; the
        // organisation users keep the default `web` guard.
        $this->app['config']->set('auth.providers.platform_admins', [
            'driver' => 'eloquent',
            'model' => PlatformAdmin::class,
        ]);
        $this->app['config']->set('auth.guards.'.PlatformSession::GUARD, [
            'driver' => 'session',
            'provider' => 'platform_admins',
        ]);
    }

    public function boot(): void
    {
        parent::boot();

        // The TOTP step (challenge or first setup): 5 attempts per minute
        // per login session, like the org admins' challenge (SEC-012).
        RateLimiter::for('platform-two-factor', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('platform-two-factor:'.$request->session()->getId()));

        if ($this->app->runningInConsole()) {
            $this->commands([CreatePlatformAdminCommand::class]);
        }
    }
}

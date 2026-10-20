<?php

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Http\Middleware\ApplyLocale;
use App\Modules\Tenancy\Http\Middleware\RequireTenant;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Http\Middleware\ShareOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Policies\OrganizationPolicy;
use App\Modules\Tenancy\Support\TenantContext;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;

class TenancyServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Organization::class, OrganizationPolicy::class);

        // Resolving the kernel applies bootstrap/app.php's middleware groups
        // first, so the additions below are not overwritten afterwards.
        /** @var Kernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('tenant', RequireTenant::class);

        // The tenant is known before sessions, route model binding and
        // authentication run (EXT-001), so every web request is resolved first.
        $kernel->prependMiddlewareToGroup('web', ResolveTenant::class);
        $kernel->prependToMiddlewarePriority(ResolveTenant::class);

        // The locale needs the session (the user), so it follows the group's
        // session middleware (DATA-011: default_locale as fallback).
        $kernel->appendMiddlewareToGroup('web', ApplyLocale::class);

        // DATA-011: the organisation settings reach every Inertia page.
        $kernel->appendMiddlewareToGroup('web', ShareOrganization::class);
    }
}

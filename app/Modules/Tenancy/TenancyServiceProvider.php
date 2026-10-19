<?php

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Http\Middleware\RequireTenant;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Support\TenantContext;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;

class TenancyServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        parent::boot();

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
    }
}

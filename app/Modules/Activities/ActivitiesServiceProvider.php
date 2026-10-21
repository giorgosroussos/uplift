<?php

namespace App\Modules\Activities;

use App\Modules\Activities\Http\Responses\ProblemDetails;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Policies\ActivityPolicy;
use App\Modules\Activities\Support\ActivityTypes;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Activity base and lifecycle, the creation wizard and the submissions
 * overview (UC-022, UC-028). Activity types plug in through the
 * ActivityType contract and the ActivityTypes registry (ARCH-004).
 */
class ActivitiesServiceProvider extends ModuleServiceProvider
{
    /**
     * Routes whose errors are RFC 9457 problem details (wizard autosave).
     */
    public const array PROBLEM_ROUTES = ['activities.store', 'activities.wizard.update'];

    public function register(): void
    {
        $this->app->singleton(ActivityTypes::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Activity::class, ActivityPolicy::class);

        // The exception handler is resolved lazily, when the first exception is rendered.
        $this->app->afterResolving(Handler::class, function (Handler $handler): void {
            $handler->renderable(function (Throwable $e, Request $request): ?JsonResponse {
                if (! $request->routeIs(...self::PROBLEM_ROUTES)) {
                    return null;
                }

                return ProblemDetails::for($e);
            });
        });
    }
}

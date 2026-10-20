<?php

namespace App\Modules\Courses;

use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Policies\CourseSpacePolicy;
use App\Modules\Courses\Policies\EnrolmentPolicy;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Course spaces and enrolment (UC-011, UC-021).
 */
class CoursesServiceProvider extends ModuleServiceProvider
{
    /**
     * SEC-010: enrolment key attempts per user and minute.
     */
    public const int ENROLMENT_ATTEMPTS_PER_MINUTE = 10;

    public function boot(): void
    {
        parent::boot();

        Gate::policy(CourseSpace::class, CourseSpacePolicy::class);
        Gate::policy(Enrolment::class, EnrolmentPolicy::class);

        RateLimiter::for('enrolment-key', fn (Request $request): Limit => Limit::perMinute(self::ENROLMENT_ATTEMPTS_PER_MINUTE)
            ->by('enrolment-key:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}

<?php

namespace App\Modules\Courses;

use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Courses\Models\GroupMembership;
use App\Modules\Courses\Policies\AnnouncementPolicy;
use App\Modules\Courses\Policies\CourseSpacePolicy;
use App\Modules\Courses\Policies\EnrolmentPolicy;
use App\Modules\Courses\Policies\GroupMembershipPolicy;
use App\Modules\Courses\Policies\GroupPolicy;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Course spaces, enrolment and announcements and groups (UC-008, UC-011, UC-019, UC-021).
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
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(GroupMembership::class, GroupMembershipPolicy::class);

        RateLimiter::for('enrolment-key', fn (Request $request): Limit => Limit::perMinute(self::ENROLMENT_ATTEMPTS_PER_MINUTE)
            ->by('enrolment-key:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}

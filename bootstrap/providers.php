<?php

use App\Modules\Activities\ActivitiesServiceProvider;
use App\Modules\Analytics\AnalyticsServiceProvider;
use App\Modules\Assessment\AssessmentServiceProvider;
use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Courses\CoursesServiceProvider;
use App\Modules\Gamification\GamificationServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Live\LiveServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Modules\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,

    // Modules (app/Modules/<Name>), in dependency order.
    TenancyServiceProvider::class,
    IdentityServiceProvider::class,
    PlatformServiceProvider::class,
    CoursesServiceProvider::class,
    ActivitiesServiceProvider::class,
    AssessmentServiceProvider::class,
    LiveServiceProvider::class,
    GamificationServiceProvider::class,
    AnalyticsServiceProvider::class,
    NotificationsServiceProvider::class,
    AuditServiceProvider::class,
];

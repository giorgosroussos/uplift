<?php

namespace App\Modules\Audit;

use App\Modules\Audit\Listeners\AuditCourseSpaceArchived;
use App\Modules\Audit\Listeners\AuditOrganizationReactivated;
use App\Modules\Audit\Listeners\AuditOrganizationRegistered;
use App\Modules\Audit\Listeners\AuditOrganizationSettingsChanged;
use App\Modules\Audit\Listeners\AuditOrganizationSuspended;
use App\Modules\Audit\Listeners\AuditUserDeactivated;
use App\Modules\Audit\Listeners\AuditUserReactivated;
use App\Modules\Audit\Listeners\AuditUserRoleChanged;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Courses\Events\CourseSpaceArchived;
use App\Modules\Identity\Events\OrganizationRegistered;
use App\Modules\Identity\Events\UserDeactivated;
use App\Modules\Identity\Events\UserReactivated;
use App\Modules\Identity\Events\UserRoleChanged;
use App\Modules\Platform\Events\OrganizationReactivated;
use App\Modules\Platform\Events\OrganizationSuspended;
use App\Modules\Tenancy\Events\OrganizationSettingsChanged;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class AuditServiceProvider extends ModuleServiceProvider
{
    /**
     * Domain event => audit listeners (subclasses of Listeners\AuditListener).
     *
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [
        OrganizationRegistered::class => [AuditOrganizationRegistered::class],
        UserRoleChanged::class => [AuditUserRoleChanged::class],
        UserDeactivated::class => [AuditUserDeactivated::class],
        UserReactivated::class => [AuditUserReactivated::class],
        OrganizationSettingsChanged::class => [AuditOrganizationSettingsChanged::class],
        CourseSpaceArchived::class => [AuditCourseSpaceArchived::class],
        OrganizationSuspended::class => [AuditOrganizationSuspended::class],
        OrganizationReactivated::class => [AuditOrganizationReactivated::class],
    ];

    public function boot(): void
    {
        parent::boot();

        // Retention of ENT-AuditEntry: 2 years, then deleted.
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('model:prune', ['--model' => [AuditEntry::class]])->daily();
        });
    }
}

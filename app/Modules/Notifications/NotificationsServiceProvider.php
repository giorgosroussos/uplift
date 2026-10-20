<?php

namespace App\Modules\Notifications;

use App\Modules\Courses\Events\AnnouncementPublished;
use App\Modules\Notifications\Http\Middleware\ShareNotifications;
use App\Modules\Notifications\Listeners\SendAnnouncementNotifications;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Policies\NotificationPolicy;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Gate;

/**
 * In-app notifications and DEC-001 notification emails (UC-007, DEC-002).
 */
class NotificationsServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [
        AnnouncementPublished::class => [SendAnnouncementNotifications::class],
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Notification::class, NotificationPolicy::class);

        // Retention of ENT-Notification: deleted 180 days after creation.
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('model:prune', ['--model' => [Notification::class]])->daily();
        });

        // DEC-002: the bell's unread count reaches every Inertia page.
        /** @var Kernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);
        $kernel->appendMiddlewareToGroup('web', ShareNotifications::class);
    }
}

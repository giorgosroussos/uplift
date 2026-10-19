<?php

namespace App\Modules\Audit;

use App\Modules\Audit\Models\AuditEntry;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class AuditServiceProvider extends ModuleServiceProvider
{
    /**
     * Domain event => audit listeners (subclasses of Listeners\AuditListener).
     *
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [];

    public function boot(): void
    {
        parent::boot();

        // Retention of ENT-AuditEntry: 2 years, then deleted.
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('model:prune', ['--model' => [AuditEntry::class]])->daily();
        });
    }
}

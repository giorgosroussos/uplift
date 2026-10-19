<?php

namespace App\Modules\Audit\Enums;

/**
 * Who performed an audited action (ENT-AuditEntry.actor_type).
 */
enum ActorType: string
{
    case User = 'user';
    case PlatformAdmin = 'platform_admin';
    case System = 'system';
}

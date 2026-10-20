<?php

namespace App\Modules\Identity\Enums;

/**
 * The one role of a user inside their organisation (SEC-004).
 *
 * Platform admins are not organisation users and are not part of this enum.
 */
enum Role: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    case OrgAdmin = 'org_admin';

    /**
     * Inertia page of this role's home (dashboard).
     */
    public function dashboardPage(): string
    {
        return match ($this) {
            self::Student => 'Identity/StudentDashboard',
            self::Teacher => 'Identity/TeacherDashboard',
            self::OrgAdmin => 'Identity/OrgAdminDashboard',
        };
    }
}

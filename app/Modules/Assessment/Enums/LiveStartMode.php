<?php

namespace App\Modules\Assessment\Enums;

/**
 * ENT-Quiz.live_start_mode of a live quiz (PHASE-5).
 */
enum LiveStartMode: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
}

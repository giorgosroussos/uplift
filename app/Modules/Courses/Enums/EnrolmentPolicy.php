<?php

namespace App\Modules\Courses\Enums;

/**
 * How students enrol in a course space (UC-011): freely, or by entering the
 * enrolment key the teacher shares.
 */
enum EnrolmentPolicy: string
{
    case Open = 'open';
    case Key = 'key';
}

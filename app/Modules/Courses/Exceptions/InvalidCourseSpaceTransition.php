<?php

namespace App\Modules\Courses\Exceptions;

use App\Modules\Courses\Enums\CourseSpaceStatus;
use DomainException;

final class InvalidCourseSpaceTransition extends DomainException
{
    public static function between(CourseSpaceStatus $from, CourseSpaceStatus $to): self
    {
        return new self("Course space status cannot change from [{$from->value}] to [{$to->value}].");
    }

    public static function initial(CourseSpaceStatus $status): self
    {
        $initial = CourseSpaceStatus::initial()->value;

        return new self("A course space must be created as [{$initial}], not [{$status->value}].");
    }
}

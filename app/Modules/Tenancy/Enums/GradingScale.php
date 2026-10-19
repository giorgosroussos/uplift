<?php

namespace App\Modules\Tenancy\Enums;

enum GradingScale: string
{
    case Scale0To10 = 'scale_0_10';
    case Scale0To100 = 'scale_0_100';
    case PointsPassMark = 'points_pass_mark';
}

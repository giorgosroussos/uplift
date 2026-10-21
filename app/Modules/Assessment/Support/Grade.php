<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Tenancy\Enums\GradingScale;

/**
 * A score on the organisation's grading scale, computed only for display
 * (DATA-003): stored points stay raw. 0-10 and 0-100 grades and the
 * percentage are rounded half-up to 1 decimal (DATA-005), e.g. 7 of 8
 * points is 8.8 on 0-10 and 87.5 %. The points scale shows the points
 * themselves; whether the attempt passed compares the exact percentage
 * with the quiz's pass mark, when it has one. All arithmetic is on
 * integers, never floating point.
 */
final class Grade
{
    /**
     * @return array{scale: string, value: string|null, percentage: string|null, earned: string, max: string, passed: bool|null}
     */
    public static function of(string $earned, string $max, GradingScale $scale, ?string $passMark = null): array
    {
        $earnedH = Points::toHundredths($earned);
        $maxH = Points::toHundredths($max);

        $percentage = $maxH > 0 ? self::tenths(Points::divideHalfUp($earnedH * 1000, $maxH)) : null;

        $value = match ($scale) {
            GradingScale::Scale0To10 => $maxH > 0 ? self::tenths(Points::divideHalfUp($earnedH * 100, $maxH)) : null,
            GradingScale::Scale0To100 => $percentage,
            GradingScale::PointsPassMark => Points::format($earnedH),
        };

        $passed = $passMark === null || $maxH <= 0
            ? null
            // earned / max * 100 >= pass mark, in hundredths on both sides.
            : $earnedH * 10000 >= Points::toHundredths($passMark) * $maxH;

        return [
            'scale' => $scale->value,
            'value' => $value,
            'percentage' => $percentage,
            'earned' => Points::format($earnedH),
            'max' => Points::format($maxH),
            'passed' => $passed,
        ];
    }

    /**
     * 88 -> "8.8", -5 -> "-0.5".
     */
    private static function tenths(int $tenths): string
    {
        $sign = $tenths < 0 ? '-' : '';
        $abs = abs($tenths);

        return sprintf('%s%d.%d', $sign, intdiv($abs, 10), $abs % 10);
    }
}

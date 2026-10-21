<?php

namespace App\Modules\Assessment\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic for points and percentages (DATA-003): values
 * with at most 2 decimal places are handled as integer hundredths, so no
 * floating point is ever involved.
 */
final class Points
{
    /**
     * "1.5" -> 150, "-0.25" -> -25.
     */
    public static function toHundredths(string $value): int
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim($value), $m) !== 1) {
            throw new InvalidArgumentException('Not a decimal with at most 2 decimal places.');
        }

        $hundredths = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');

        return $m[1] === '-' ? -$hundredths : $hundredths;
    }

    /**
     * 150 -> "1.50", -25 -> "-0.25".
     */
    public static function format(int $hundredths): string
    {
        $sign = $hundredths < 0 ? '-' : '';
        $abs = abs($hundredths);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    /**
     * numerator / denominator rounded half-up (away from zero for halves)
     * to an integer, e.g. 200 / 3 -> 67, 25 / 2 -> 13.
     */
    public static function divideHalfUp(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('The denominator must be positive.');
        }

        $abs = intdiv(2 * abs($numerator) + $denominator, 2 * $denominator);

        return $numerator < 0 ? -$abs : $abs;
    }
}

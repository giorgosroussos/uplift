<?php

use App\Modules\Assessment\Support\Grade;
use App\Modules\Tenancy\Enums\GradingScale;

/*
| DATA-003 / DATA-005: conversion to the organisation's grading scale at
| display time, rounded half-up to 1 decimal with exact arithmetic.
*/

it('UC-017: Given a 0-10 scale and a score of 7 out of 8 points, Then the result shows 8.8', function () {
    expect(Grade::of('7.00', '8.00', GradingScale::Scale0To10))->toMatchArray([
        'scale' => 'scale_0_10',
        'value' => '8.8',
        'percentage' => '87.5',
        'earned' => '7.00',
        'max' => '8.00',
        'passed' => null,
    ]);
});

it('rounds half-up to 1 decimal on both scales', function (string $earned, string $max, GradingScale $scale, string $value) {
    expect(Grade::of($earned, $max, $scale)['value'])->toBe($value);
})->with([
    '0-10: 1/3' => ['1.00', '3.00', GradingScale::Scale0To10, '3.3'],
    '0-10: 2/3' => ['2.00', '3.00', GradingScale::Scale0To10, '6.7'],
    '0-10: 0.25/2 = 1.25 -> 1.3' => ['0.25', '2.00', GradingScale::Scale0To10, '1.3'],
    '0-10: full' => ['8.00', '8.00', GradingScale::Scale0To10, '10.0'],
    '0-10: zero' => ['0.00', '8.00', GradingScale::Scale0To10, '0.0'],
    '0-100: 1/3' => ['1.00', '3.00', GradingScale::Scale0To100, '33.3'],
    '0-100: 1/8 = 12.5' => ['1.00', '8.00', GradingScale::Scale0To100, '12.5'],
    '0-100: 1/16 = 6.25 -> 6.3' => ['1.00', '16.00', GradingScale::Scale0To100, '6.3'],
    'points' => ['7.50', '8.00', GradingScale::PointsPassMark, '7.50'],
]);

it('judges the pass mark on the exact percentage', function (string $earned, string $passMark, bool $passed) {
    expect(Grade::of($earned, '8.00', GradingScale::PointsPassMark, $passMark)['passed'])->toBe($passed);
})->with([
    'exactly at the mark' => ['4.00', '50.00', true],
    'just below' => ['3.99', '50.00', false],
    // 87.375 % shows as 87.4 and stays below an 87.4 mark.
    'rounding does not pass' => ['6.99', '87.40', false],
]);

it('converts nothing without maximum points', function () {
    expect(Grade::of('0.00', '0.00', GradingScale::Scale0To10, '50.00'))->toMatchArray([
        'value' => null,
        'percentage' => null,
        'passed' => null,
    ]);
});

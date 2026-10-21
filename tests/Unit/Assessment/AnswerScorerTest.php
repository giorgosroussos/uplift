<?php

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Support\AnswerScorer;
use App\Modules\Assessment\Support\Points;

/*
| SCOPE-024 scoring rules in exact hundredths of a point (DATA-003).
*/

it('converts decimals to hundredths and back exactly', function (string $value, int $hundredths, string $formatted) {
    expect(Points::toHundredths($value))->toBe($hundredths)
        ->and(Points::format($hundredths))->toBe($formatted);
})->with([
    ['1', 100, '1.00'],
    ['1.5', 150, '1.50'],
    ['0.25', 25, '0.25'],
    ['-0.25', -25, '-0.25'],
    ['100.00', 10000, '100.00'],
]);

it('refuses values with more than 2 decimals', function () {
    Points::toHundredths('0.125');
})->throws(InvalidArgumentException::class);

it('rounds half-up', function (int $numerator, int $denominator, int $expected) {
    expect(Points::divideHalfUp($numerator, $denominator))->toBe($expected);
})->with([
    [200, 3, 67],
    [100, 3, 33],
    [25, 2, 13],
    [-25, 2, -13],
    [50, 2, 25],
]);

it('scores single choice and true/false all or nothing', function (array $selected, int $expected) {
    expect(AnswerScorer::choice(QuestionType::SingleChoice, 100, ['a'], $selected))->toBe($expected)
        ->and(AnswerScorer::choice(QuestionType::TrueFalse, 100, ['a'], $selected))->toBe($expected);
})->with([
    'correct' => [['a'], 100],
    'wrong' => [['b'], 0],
    'none' => [[], 0],
    'both' => [['a', 'b'], 0],
]);

it('scores multiple choice as points x max(0, correct - wrong) / correct options', function (array $selected, int $expected) {
    expect(AnswerScorer::choice(QuestionType::MultipleChoice, 100, ['a', 'b'], $selected))->toBe($expected);
})->with([
    'both correct' => [['a', 'b'], 100],
    'both correct and 1 wrong' => [['a', 'b', 'c'], 50],
    'all 4' => [['a', 'b', 'c', 'd'], 0],
    'one correct' => [['a'], 50],
    'one correct, one wrong' => [['a', 'c'], 0],
    'none' => [[], 0],
]);

it('scores matching as points x correct matches / pairs, rounded half-up', function () {
    expect(AnswerScorer::matching(100, 3, 2))->toBe(67)
        ->and(AnswerScorer::matching(100, 3, 1))->toBe(33)
        ->and(AnswerScorer::matching(325, 2, 1))->toBe(163)
        ->and(AnswerScorer::matching(100, 3, 0))->toBe(0);
});

it('applies negative marking only to answered questions that earn 0', function () {
    // -0.25 on a wrong 1-point answer with 25 %; nothing without negative marking.
    expect(AnswerScorer::withNegativeMarking(0, true, 100, '25.00'))->toBe(-25)
        ->and(AnswerScorer::withNegativeMarking(0, true, 100, null))->toBe(0)
        // Partial credit keeps its credit; unanswered questions are not penalised.
        ->and(AnswerScorer::withNegativeMarking(50, true, 100, '25.00'))->toBe(50)
        ->and(AnswerScorer::withNegativeMarking(0, false, 100, '25.00'))->toBe(0)
        // Rounded half-up: 12.5 % of 1 point = 0.125 -> -0.13.
        ->and(AnswerScorer::withNegativeMarking(0, true, 100, '12.50'))->toBe(-13);
});

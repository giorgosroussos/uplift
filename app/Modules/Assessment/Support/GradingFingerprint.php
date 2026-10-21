<?php

namespace App\Modules\Assessment\Support;

/**
 * What of a quiz's wizard data decides how attempts are graded, in a form
 * that compares equal exactly when grading would stay the same (UC-022:
 * once a quiz has submissions, such changes are refused). Works on saved
 * and on not yet validated input alike.
 */
final class GradingFingerprint
{
    /**
     * Negative marking with its percentage, and the pass mark.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function settings(array $settings): array
    {
        $negative = filter_var($settings['negative_marking'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'negative_marking' => $negative,
            'negative_mark_fraction' => $negative ? self::number($settings['negative_mark_fraction'] ?? null) : null,
            'pass_mark_percentage' => self::number($settings['pass_mark_percentage'] ?? null),
        ];
    }

    /**
     * The questions (by id, in any order) with their type, points, options
     * with which are correct, and pairs.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function questions(array $content): array
    {
        $questions = is_array($content['questions'] ?? null) ? $content['questions'] : [];
        $fingerprint = [];

        foreach ($questions as $index => $question) {
            if (! is_array($question)) {
                $fingerprint["invalid:{$index}"] = null;

                continue;
            }

            $id = is_string($question['id'] ?? null) ? $question['id'] : "new:{$index}";
            $options = [];

            foreach (is_array($question['options'] ?? null) ? $question['options'] : [] as $position => $option) {
                $key = is_array($option) && is_string($option['id'] ?? null) ? $option['id'] : "new:{$position}";
                $options[$key] = is_array($option) && filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            $pairs = [];

            foreach (is_array($question['pairs'] ?? null) ? $question['pairs'] : [] as $position => $pair) {
                $pairs[] = is_array($pair) && is_string($pair['id'] ?? null) ? $pair['id'] : "new:{$position}";
            }

            ksort($options);
            sort($pairs);

            $fingerprint[$id] = [
                'type' => $question['type'] ?? null,
                'points' => self::number($question['points'] ?? null),
                'options' => $options,
                'pairs' => $pairs,
            ];
        }

        ksort($fingerprint);

        return $fingerprint;
    }

    private static function number(mixed $value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return is_scalar($value) ? (string) $value : null;
        }

        return QuestionRules::decimal($value);
    }
}

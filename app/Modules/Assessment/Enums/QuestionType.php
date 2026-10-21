<?php

namespace App\Modules\Assessment\Enums;

/**
 * The five question types of ENT-Question and their DATA-016 shape: choice
 * types carry options (ENT-ChoiceOption), matching carries pairs
 * (ENT-MatchingPair), free text carries neither.
 */
enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case Matching = 'matching';
    case FreeText = 'free_text';

    /**
     * DATA-016: the fixed, stored texts of the two true/false options; they
     * are shown only through translation keys.
     */
    public const array TRUE_FALSE_TEXTS = ['true', 'false'];

    /**
     * DATA-016: options of a choice question and pairs of a matching question.
     */
    public const int MIN_ITEMS = 2;

    public const int MAX_ITEMS = 10;

    public function hasOptions(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleChoice, self::TrueFalse], true);
    }

    public function hasPairs(): bool
    {
        return $this === self::Matching;
    }

    /**
     * Exactly one correct option (single choice, true/false) rather than at
     * least one (multiple choice).
     */
    public function hasSingleCorrectOption(): bool
    {
        return $this === self::SingleChoice || $this === self::TrueFalse;
    }

    /**
     * SCOPE-023: choice, true/false and matching answers are graded
     * automatically on submission; free-text answers wait for the teacher.
     */
    public function isAutoGraded(): bool
    {
        return $this !== self::FreeText;
    }
}

<?php

namespace App\Modules\Assessment\Enums;

/**
 * ENT-Quiz.mode. A self-paced quiz is taken in attempts; a live quiz runs
 * in a live session (PHASE-5) and never takes self-paced attempts
 * (SCOPE-016). Live quizzes have no free-text questions (UC-022).
 */
enum QuizMode: string
{
    case SelfPaced = 'self_paced';
    case Live = 'live';

    /**
     * The modes the wizard offers in this phase (live quizzes: PHASE-5).
     *
     * @return list<string>
     */
    public static function offered(): array
    {
        return [self::SelfPaced->value];
    }

    public function allowsQuestionType(QuestionType $type): bool
    {
        return ! ($this === self::Live && $type === QuestionType::FreeText);
    }
}

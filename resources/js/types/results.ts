/**
 * UC-017 (pages Assessment/Results/*) and UC-026 (Assessment/Grading/*):
 * results of submitted quiz attempts and their grading.
 */
import type { QuestionType } from '@/types/assessment';
import type { AttemptScore, AttemptStatus } from '@/types/attempts';

export type AnswerGradingStatus = 'pending' | 'auto_graded' | 'graded';

export type ResultAttempt = {
    id: string;
    number: number;
    status: AttemptStatus;
    submitted_at: string | null;
    final: boolean;
    /** Null while the quiz's score visibility hides it. */
    score: AttemptScore | null;
    has_feedback: boolean;
    /** The group's name for a group attempt (same grade for every member). */
    group: string | null;
};

export type ActivityResults = {
    activity_id: string;
    title: string;
    course_space_title: string;
    last_submitted_at: string | null;
    attempts: ResultAttempt[];
};

export type ReviewOption = {
    id: string;
    text: string;
    selected: boolean;
    /** Null when the correct answers are not shown. */
    correct: boolean | null;
};

export type ReviewMatch = {
    left: string;
    chosen: string | null;
    correct: string | null;
    is_correct: boolean | null;
};

export type ReviewQuestion = {
    /** The quiz question (placement) id. */
    id: string;
    answer_id: string | null;
    type: QuestionType;
    prompt: string;
    points: string;
    options: ReviewOption[];
    matches: ReviewMatch[];
    text: string | null;
    grading_status: AnswerGradingStatus | null;
    /** Null while ungraded or when the score is not shown. */
    points_awarded: string | null;
    feedback: string | null;
};

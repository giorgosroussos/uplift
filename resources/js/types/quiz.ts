import type {
    ChoiceOptionInput,
    MatchingPairInput,
    QuestionType,
} from '@/types/assessment';

export const QUIZ_MODES = ['self_paced', 'live'] as const;

export type QuizMode = (typeof QUIZ_MODES)[number];

export const LIVE_START_MODES = ['manual', 'scheduled'] as const;

/** How a live quiz starts (SCOPE-021). */
export type LiveStartMode = (typeof LIVE_START_MODES)[number];

export const RESULT_VISIBILITIES = [
    'immediate',
    'after_deadline',
    'hidden',
] as const;

export type ResultVisibility = (typeof RESULT_VISIBILITIES)[number];

/**
 * The settings step of a quiz (UC-022); percentages from 0 to 100. A live
 * quiz uses time_limit_minutes as its duration; its scheduled start is a
 * UTC ISO timestamp.
 */
export type QuizSettings = {
    mode: QuizMode;
    time_limit_minutes: number | null;
    live_start_mode: LiveStartMode | null;
    live_scheduled_start_at: string | null;
    max_attempts: number;
    negative_marking: boolean;
    negative_mark_fraction: string | null;
    pass_mark_percentage: string | null;
    score_visibility: ResultVisibility;
    answers_visibility: ResultVisibility;
};

/** One question of the content step as saved and sent. */
export type QuizQuestionPayload = {
    id: string;
    type: QuestionType;
    prompt: string;
    points: string;
    options: { id: string | null; text: string; is_correct: boolean }[];
    pairs: { id: string | null; left_text: string; right_text: string }[];
};

/** One question in the editor; `key` identifies list items, it is not sent. */
export type QuizQuestionForm = {
    id: string;
    type: QuestionType;
    prompt: string;
    points: string;
    options: ChoiceOptionInput[];
    pairs: MatchingPairInput[];
};

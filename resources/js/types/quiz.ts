import type {
    ChoiceOptionInput,
    MatchingPairInput,
    QuestionType,
} from '@/types/assessment';

export type QuizMode = 'self_paced' | 'live';

export const RESULT_VISIBILITIES = [
    'immediate',
    'after_deadline',
    'hidden',
] as const;

export type ResultVisibility = (typeof RESULT_VISIBILITIES)[number];

/** The settings step of a quiz (UC-022); percentages from 0 to 100. */
export type QuizSettings = {
    mode: QuizMode;
    time_limit_minutes: number | null;
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

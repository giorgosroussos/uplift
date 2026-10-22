export const QUESTION_TYPES = [
    'single_choice',
    'multiple_choice',
    'true_false',
    'matching',
    'free_text',
] as const;

export type QuestionType = (typeof QUESTION_TYPES)[number];

/** DATA-016: options and pairs per question. */
export const MIN_ITEMS = 2;
export const MAX_ITEMS = 10;

/** DATA-016: stored texts of true/false options, shown through translation keys. */
export const TRUE_FALSE_TEXTS = ['true', 'false'] as const;

export type BankQuestionSummary = {
    id: string;
    type: QuestionType;
    prompt: string;
    points: string;
    items_count: number;
    created_at: string | null;
};

export type ChoiceOption = {
    id: string;
    text: string;
    is_correct: boolean;
};

export type MatchingPair = {
    id: string;
    left_text: string;
    right_text: string;
};

export type BankQuestion = {
    id: string;
    type: QuestionType;
    prompt: string;
    points: string;
    options: ChoiceOption[];
    pairs: MatchingPair[];
};

/** `key` identifies an item in the drag-and-drop list; it is not sent. */
export type ChoiceOptionInput = {
    key: string;
    id: string | null;
    text: string;
    is_correct: boolean;
};

export type MatchingPairInput = {
    key: string;
    id: string | null;
    left_text: string;
    right_text: string;
};

export type QuestionForm = {
    type: QuestionType;
    prompt: string;
    points: string;
    options: ChoiceOptionInput[];
    pairs: MatchingPairInput[];
};

/** UC-030, DATA-017: one data row of an uploaded question CSV. */
export type QuestionImportRow = {
    line: number;
    type: string;
    prompt: string;
    valid: boolean;
    errors: string[];
};

/** UC-030, DATA-017: the report of a pending question import. */
export type QuestionImportReport = {
    id: string;
    refusal: string | null;
    valid_count: number;
    invalid_count: number;
    rows: QuestionImportRow[];
};

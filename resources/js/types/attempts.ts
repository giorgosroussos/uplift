/**
 * UC-012: a student takes a self-paced quiz (pages Assessment/Attempts/*).
 */
import type { ActivityStatus } from '@/types/activities';
import type { QuestionType } from '@/types/assessment';

export type AttemptStatus = 'in_progress' | 'submitted' | 'graded';

export type GradingScale = 'scale_0_10' | 'scale_0_100' | 'points_pass_mark';

/**
 * A score on the organisation's grading scale, converted and rounded on
 * the server (DATA-003, DATA-005). `value` is the 0-10 or 0-100 grade, or
 * the earned points on the points scale; `passed` is null without a pass
 * mark.
 */
export type Grade = {
    scale: GradingScale;
    value: string | null;
    percentage: string | null;
    earned: string;
    max: string;
    passed: boolean | null;
};

/** Earned and maximum points as exact decimal strings. */
export type AttemptScore = {
    earned: string;
    max: string;
    provisional: boolean;
    grade: Grade;
};

export type StudentQuiz = {
    activity_id: string;
    title: string;
    description: string | null;
    status: ActivityStatus;
    course_space_title: string;
    deadline_at: string | null;
    time_limit_minutes: number | null;
    max_attempts: number;
    question_count: number;
    negative_marking: boolean;
    negative_mark_fraction: string | null;
};

export type AttemptSummary = {
    id: string;
    number: number;
    status: AttemptStatus;
    started_at: string;
    submitted_at: string | null;
    score: AttemptScore | null;
};

export type AttemptDetails = {
    id: string;
    number: number;
    status: AttemptStatus;
    started_at: string;
    ends_at: string | null;
    /** Seconds left by the server's clock, or null without an end. */
    remaining_seconds: number | null;
    submitted_at: string | null;
};

export type AttemptItem = {
    id: string;
    text: string;
};

export type AttemptQuestion = {
    /** The quiz question (placement) id. */
    id: string;
    type: QuestionType;
    prompt: string;
    points: string;
    options: AttemptItem[];
    /** Matching: left items (pair ids). */
    left: AttemptItem[];
    /** Matching: right items (opaque ids, shuffled). */
    right: AttemptItem[];
};

export type AttemptMatch = {
    left_id: string;
    right_id: string;
};

export type AttemptAnswer = {
    option_ids: string[];
    matches: AttemptMatch[];
    text: string | null;
};

/**
 * Helpers of taking a quiz (UC-012): the countdown display and edits of an
 * answer. Timing decisions are the server's (DATA-004); the countdown only
 * shows the time left that the server reported.
 */
import type { AttemptAnswer, AttemptQuestion } from '@/types/attempts';

export function emptyAnswer(): AttemptAnswer {
    return { option_ids: [], matches: [], text: null };
}

/**
 * 75 -> "1:15", 3725 -> "1:02:05"; never negative.
 */
export function formatRemaining(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = String(total % 60).padStart(2, '0');

    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${secs}`
        : `${minutes}:${secs}`;
}

/**
 * Selects or unselects an option: single choice and true/false keep one
 * option, multiple choice toggles it.
 */
export function toggleOption(
    question: AttemptQuestion,
    answer: AttemptAnswer,
    optionId: string,
): AttemptAnswer {
    if (question.type !== 'multiple_choice') {
        return { ...answer, option_ids: [optionId] };
    }

    const selected = answer.option_ids.includes(optionId)
        ? answer.option_ids.filter((id) => id !== optionId)
        : [...answer.option_ids, optionId];

    return { ...answer, option_ids: selected };
}

/**
 * Matches a left item to a right item, or clears its match (null).
 */
export function setMatch(
    answer: AttemptAnswer,
    leftId: string,
    rightId: string | null,
): AttemptAnswer {
    const matches = answer.matches.filter((m) => m.left_id !== leftId);

    if (rightId) {
        matches.push({ left_id: leftId, right_id: rightId });
    }

    return { ...answer, matches };
}

export function matchOf(answer: AttemptAnswer, leftId: string): string {
    return answer.matches.find((m) => m.left_id === leftId)?.right_id ?? '';
}

/**
 * The autosave payload of an answer for its question type.
 */
export function answerPayload(
    question: AttemptQuestion,
    answer: AttemptAnswer,
): Record<string, unknown> {
    if (question.type === 'matching') {
        return { matches: answer.matches };
    }

    if (question.type === 'free_text') {
        return { text: answer.text };
    }

    return { option_ids: answer.option_ids };
}

export function isAnswered(
    question: AttemptQuestion,
    answer: AttemptAnswer | undefined,
): boolean {
    if (!answer) {
        return false;
    }

    if (question.type === 'matching') {
        return answer.matches.length > 0;
    }

    if (question.type === 'free_text') {
        return (answer.text ?? '').trim() !== '';
    }

    return answer.option_ids.length > 0;
}

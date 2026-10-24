import { emptyQuestionForm, hasOptions, hasPairs } from '@/lib/questionForm';
import type { StepData, StepErrors } from '@/types/activities';
import type { BankQuestion, QuestionForm } from '@/types/assessment';
import type {
    QuizQuestionForm,
    QuizQuestionPayload,
    QuizSettings,
} from '@/types/quiz';

/*
 * State of the quiz steps of the activity wizard (UC-022). Every question,
 * option and pair carries a UUID made here for new ones, so autosaving the
 * same list again changes nothing on the server. Copying a bank question
 * makes a new question with new ids (DATA-008): the copy is the quiz's own.
 */

let counter = 0;

function nextKey(): string {
    counter += 1;

    return `quiz-item-${counter}`;
}

/**
 * A random (version 4) UUID; works outside secure contexts too.
 */
export function uuid(): string {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    const hex = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function defaultSettings(): QuizSettings {
    return {
        mode: 'self_paced',
        time_limit_minutes: null,
        live_start_mode: null,
        live_scheduled_start_at: null,
        max_attempts: 1,
        negative_marking: false,
        negative_mark_fraction: null,
        pass_mark_percentage: null,
        score_visibility: 'immediate',
        answers_visibility: 'after_deadline',
    };
}

/**
 * The saved settings, completed with the defaults for a new quiz.
 */
export function settingsFrom(data: StepData | null): QuizSettings {
    return { ...defaultSettings(), ...((data ?? {}) as Partial<QuizSettings>) };
}

/**
 * What the settings step saves. A live quiz (SCOPE-021) starts manually
 * unless scheduled, has a scheduled time only when scheduled and never has
 * negative marking (SCOPE-022); a self-paced quiz has no live start.
 */
export function settingsPayload(settings: QuizSettings): QuizSettings {
    if (settings.mode === 'live') {
        const startMode = settings.live_start_mode ?? 'manual';

        return {
            ...settings,
            live_start_mode: startMode,
            live_scheduled_start_at:
                startMode === 'scheduled'
                    ? settings.live_scheduled_start_at
                    : null,
            negative_marking: false,
            negative_mark_fraction: null,
        };
    }

    return {
        ...settings,
        live_start_mode: null,
        live_scheduled_start_at: null,
        negative_mark_fraction: settings.negative_marking
            ? settings.negative_mark_fraction
            : null,
    };
}

function withKeys(question: QuizQuestionPayload): QuizQuestionForm {
    return {
        ...question,
        options: question.options.map((option) => ({
            key: nextKey(),
            ...option,
        })),
        pairs: question.pairs.map((pair) => ({ key: nextKey(), ...pair })),
    };
}

/**
 * The saved questions of the content step, in order.
 */
export function questionsFrom(data: StepData | null): QuizQuestionForm[] {
    const questions = (data?.questions ?? []) as QuizQuestionPayload[];

    return questions.map(withKeys);
}

export function newQuestion(): QuizQuestionForm {
    return ensureIds({ id: uuid(), ...emptyQuestionForm() });
}

/**
 * A copy of a bank question for the quiz, with new ids for the question,
 * its options and its pairs (DATA-008).
 */
export function copyFromBank(question: BankQuestion): QuizQuestionForm {
    return withKeys({
        id: uuid(),
        type: question.type,
        prompt: question.prompt,
        points: question.points,
        options: question.options.map((option) => ({
            id: uuid(),
            text: option.text,
            is_correct: option.is_correct,
        })),
        pairs: question.pairs.map((pair) => ({
            id: uuid(),
            left_text: pair.left_text,
            right_text: pair.right_text,
        })),
    });
}

/**
 * Gives new options and pairs their UUIDs (in place) and returns the question.
 */
export function ensureIds<T extends QuestionForm>(question: T): T {
    for (const option of question.options) {
        option.id ??= uuid();
    }

    for (const pair of question.pairs) {
        pair.id ??= uuid();
    }

    return question;
}

/**
 * What the content step saves: the questions in their order, each with
 * only the items its type has, without list keys.
 */
export function quizPayload(questions: QuizQuestionForm[]): {
    questions: QuizQuestionPayload[];
} {
    return {
        questions: questions.map((question) => ({
            id: question.id,
            type: question.type,
            prompt: question.prompt,
            points: String(question.points),
            options: hasOptions(question.type)
                ? question.options.map(({ id, text, is_correct }) => ({
                      id: id ?? uuid(),
                      text,
                      is_correct,
                  }))
                : [],
            pairs: hasPairs(question.type)
                ? question.pairs.map(({ id, left_text, right_text }) => ({
                      id: id ?? uuid(),
                      left_text,
                      right_text,
                  }))
                : [],
        })),
    };
}

/**
 * The errors of one question (questions.<index>.<field>) keyed by field,
 * for the question builder.
 */
export function questionErrors(
    errors: StepErrors,
    index: number,
): Record<string, string> {
    const prefix = `questions.${index}.`;
    const own: Record<string, string> = {};

    for (const [field, message] of Object.entries(errors)) {
        if (field.startsWith(prefix)) {
            own[field.slice(prefix.length)] = message;
        }
    }

    return own;
}

/**
 * Whether a question has any error, to show it expanded.
 */
export function hasQuestionErrors(errors: StepErrors, index: number): boolean {
    return Object.keys(questionErrors(errors, index)).length > 0;
}

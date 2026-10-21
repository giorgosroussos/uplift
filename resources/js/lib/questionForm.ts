import type {
    BankQuestion,
    ChoiceOptionInput,
    MatchingPairInput,
    QuestionForm,
    QuestionType,
} from '@/types/assessment';
import { MIN_ITEMS, TRUE_FALSE_TEXTS } from '@/types/assessment';

/*
 * State of the question builder (UC-023): which items a question type has,
 * what the builder starts with when the type changes, and what is sent.
 * The server validates everything again (DATA-016).
 */

let counter = 0;

function nextKey(): string {
    counter += 1;

    return `item-${counter}`;
}

export function hasOptions(type: QuestionType): boolean {
    return (
        type === 'single_choice' ||
        type === 'multiple_choice' ||
        type === 'true_false'
    );
}

export function hasPairs(type: QuestionType): boolean {
    return type === 'matching';
}

/** Exactly one correct option rather than at least one. */
export function hasSingleCorrect(type: QuestionType): boolean {
    return type === 'single_choice' || type === 'true_false';
}

export function blankOption(): ChoiceOptionInput {
    return { key: nextKey(), id: null, text: '', is_correct: false };
}

export function blankPair(): MatchingPairInput {
    return { key: nextKey(), id: null, left_text: '', right_text: '' };
}

export function trueFalseOptions(): ChoiceOptionInput[] {
    return TRUE_FALSE_TEXTS.map((text, index) => ({
        key: nextKey(),
        id: null,
        text,
        is_correct: index === 0,
    }));
}

function isTrueFalseSet(options: ChoiceOptionInput[]): boolean {
    return (
        options.length === TRUE_FALSE_TEXTS.length &&
        options.every((option) =>
            (TRUE_FALSE_TEXTS as readonly string[]).includes(option.text),
        )
    );
}

/**
 * The options and pairs a question keeps when its type changes to `type`:
 * true/false gets its two fixed options, other choice types keep the
 * written options (but never the true/false ones), matching keeps its
 * pairs, free text has neither.
 */
export function itemsForType(
    type: QuestionType,
    options: ChoiceOptionInput[],
    pairs: MatchingPairInput[],
): { options: ChoiceOptionInput[]; pairs: MatchingPairInput[] } {
    if (type === 'true_false') {
        return {
            options: isTrueFalseSet(options) ? options : trueFalseOptions(),
            pairs: [],
        };
    }

    if (hasOptions(type)) {
        let kept = isTrueFalseSet(options) ? [] : options;

        if (type === 'single_choice') {
            let seen = false;
            kept = kept.map((option) => {
                const correct = option.is_correct && !seen;
                seen = seen || correct;

                return { ...option, is_correct: correct };
            });
        }

        while (kept.length < MIN_ITEMS) {
            kept = [...kept, blankOption()];
        }

        return { options: kept, pairs: [] };
    }

    if (hasPairs(type)) {
        let kept = pairs;

        while (kept.length < MIN_ITEMS) {
            kept = [...kept, blankPair()];
        }

        return { options: [], pairs: kept };
    }

    return { options: [], pairs: [] };
}

export function emptyQuestionForm(): QuestionForm {
    return {
        type: 'single_choice',
        prompt: '',
        points: '1',
        ...itemsForType('single_choice', [], []),
    };
}

export function questionFormFrom(question: BankQuestion): QuestionForm {
    return {
        type: question.type,
        prompt: question.prompt,
        points: question.points,
        options: question.options.map((option) => ({
            key: nextKey(),
            ...option,
        })),
        pairs: question.pairs.map((pair) => ({ key: nextKey(), ...pair })),
    };
}

/** What is sent: the items in their current order, without list keys. */
export function questionPayload(form: QuestionForm) {
    return {
        type: form.type,
        prompt: form.prompt,
        points: form.points,
        options: hasOptions(form.type)
            ? form.options.map(({ id, text, is_correct }) => ({
                  id,
                  text,
                  is_correct,
              }))
            : [],
        pairs: hasPairs(form.type)
            ? form.pairs.map(({ id, left_text, right_text }) => ({
                  id,
                  left_text,
                  right_text,
              }))
            : [],
    };
}

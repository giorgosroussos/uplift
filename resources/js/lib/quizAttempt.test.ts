import { describe, expect, it } from 'vitest';
import {
    answerPayload,
    emptyAnswer,
    formatRemaining,
    isAnswered,
    matchOf,
    setMatch,
    toggleOption,
} from '@/lib/quizAttempt';
import type { AttemptQuestion } from '@/types/attempts';

function question(type: AttemptQuestion['type']): AttemptQuestion {
    return {
        id: 'q1',
        type,
        prompt: 'Question?',
        points: '1.00',
        options: [
            { id: 'a', text: 'A' },
            { id: 'b', text: 'B' },
        ],
        left: [{ id: 'l1', text: 'France' }],
        right: [{ id: 'r1', text: 'Paris' }],
    };
}

describe('formatRemaining', () => {
    it('shows minutes and seconds, and hours when needed', () => {
        expect(formatRemaining(75)).toBe('1:15');
        expect(formatRemaining(600)).toBe('10:00');
        expect(formatRemaining(3725)).toBe('1:02:05');
    });

    it('never shows negative time', () => {
        expect(formatRemaining(-5)).toBe('0:00');
    });
});

describe('toggleOption', () => {
    it('keeps a single option for single choice and true/false', () => {
        const once = toggleOption(
            question('single_choice'),
            emptyAnswer(),
            'a',
        );

        expect(
            toggleOption(question('single_choice'), once, 'b').option_ids,
        ).toEqual(['b']);
        expect(
            toggleOption(question('true_false'), once, 'b').option_ids,
        ).toEqual(['b']);
    });

    it('toggles options of a multiple-choice question', () => {
        const q = question('multiple_choice');
        const both = toggleOption(q, toggleOption(q, emptyAnswer(), 'a'), 'b');

        expect(both.option_ids).toEqual(['a', 'b']);
        expect(toggleOption(q, both, 'a').option_ids).toEqual(['b']);
    });
});

describe('setMatch', () => {
    it('replaces and clears the match of a left item', () => {
        const matched = setMatch(emptyAnswer(), 'l1', 'r1');

        expect(matchOf(matched, 'l1')).toBe('r1');
        expect(setMatch(matched, 'l1', 'r2').matches).toEqual([
            { left_id: 'l1', right_id: 'r2' },
        ]);
        expect(setMatch(matched, 'l1', null).matches).toEqual([]);
        expect(matchOf(emptyAnswer(), 'l1')).toBe('');
    });
});

describe('answerPayload and isAnswered', () => {
    it('sends only the part of the answer that fits the question type', () => {
        const answer = {
            option_ids: ['a'],
            matches: [{ left_id: 'l1', right_id: 'r1' }],
            text: 'Hi',
        };

        expect(answerPayload(question('single_choice'), answer)).toEqual({
            option_ids: ['a'],
        });
        expect(answerPayload(question('matching'), answer)).toEqual({
            matches: answer.matches,
        });
        expect(answerPayload(question('free_text'), answer)).toEqual({
            text: 'Hi',
        });
    });

    it('treats empty selections, matches and blank text as unanswered', () => {
        expect(isAnswered(question('single_choice'), undefined)).toBe(false);
        expect(isAnswered(question('multiple_choice'), emptyAnswer())).toBe(
            false,
        );
        expect(isAnswered(question('matching'), emptyAnswer())).toBe(false);
        expect(
            isAnswered(question('free_text'), { ...emptyAnswer(), text: '  ' }),
        ).toBe(false);
        expect(
            isAnswered(question('free_text'), { ...emptyAnswer(), text: 'x' }),
        ).toBe(true);
    });
});

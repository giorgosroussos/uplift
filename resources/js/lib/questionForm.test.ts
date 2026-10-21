import { describe, expect, it } from 'vitest';
import {
    blankOption,
    emptyQuestionForm,
    itemsForType,
    questionFormFrom,
    questionPayload,
} from './questionForm';

describe('question builder state (UC-023, DATA-016)', () => {
    it('starts as a single-choice question with two blank options', () => {
        const form = emptyQuestionForm();

        expect(form.type).toBe('single_choice');
        expect(form.options).toHaveLength(2);
        expect(form.pairs).toEqual([]);
    });

    it('gives a true/false question the stored texts true and false', () => {
        const { options, pairs } = itemsForType(
            'true_false',
            [blankOption()],
            [],
        );

        expect(options.map((option) => option.text)).toEqual(['true', 'false']);
        expect(options.filter((option) => option.is_correct)).toHaveLength(1);
        expect(pairs).toEqual([]);
    });

    it('does not carry true/false options over to another choice type', () => {
        const trueFalse = itemsForType('true_false', [], []).options;
        const { options } = itemsForType('single_choice', trueFalse, []);

        expect(options.map((option) => option.text)).toEqual(['', '']);
    });

    it('keeps at most one correct option when switching to single choice', () => {
        const options = [
            { ...blankOption(), text: 'A', is_correct: true },
            { ...blankOption(), text: 'B', is_correct: true },
        ];

        const result = itemsForType('single_choice', options, []).options;

        expect(result.map((option) => option.is_correct)).toEqual([
            true,
            false,
        ]);
    });

    it('gives a matching question two blank pairs and free text nothing', () => {
        expect(itemsForType('matching', [], []).pairs).toHaveLength(2);
        expect(itemsForType('free_text', [blankOption()], [])).toEqual({
            options: [],
            pairs: [],
        });
    });

    it('sends the items in their current order without list keys', () => {
        const form = questionFormFrom({
            id: 'q1',
            type: 'single_choice',
            prompt: 'Pick A',
            points: '1.00',
            options: [
                { id: 'a', text: 'A', is_correct: true },
                { id: 'b', text: 'B', is_correct: false },
            ],
            pairs: [],
        });

        form.options = [form.options[1], form.options[0]];

        expect(questionPayload(form)).toEqual({
            type: 'single_choice',
            prompt: 'Pick A',
            points: '1.00',
            options: [
                { id: 'b', text: 'B', is_correct: false },
                { id: 'a', text: 'A', is_correct: true },
            ],
            pairs: [],
        });
    });

    it('sends no options or pairs for a free-text question', () => {
        const form = { ...emptyQuestionForm(), type: 'free_text' as const };

        expect(questionPayload(form).options).toEqual([]);
        expect(questionPayload(form).pairs).toEqual([]);
    });
});

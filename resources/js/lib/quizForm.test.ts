import { describe, expect, it } from 'vitest';
import {
    copyFromBank,
    ensureIds,
    newQuestion,
    questionErrors,
    questionsFrom,
    quizPayload,
    settingsFrom,
    uuid,
} from '@/lib/quizForm';
import type { BankQuestion } from '@/types/assessment';

const UUID =
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

const bankQuestion: BankQuestion = {
    id: 'bank-1',
    type: 'single_choice',
    prompt: 'Capital of Greece?',
    points: '2.00',
    options: [
        { id: 'opt-1', text: 'Athens', is_correct: true },
        { id: 'opt-2', text: 'Sparta', is_correct: false },
    ],
    pairs: [],
};

describe('uuid', () => {
    it('makes distinct version 4 UUIDs', () => {
        const a = uuid();

        expect(a).toMatch(UUID);
        expect(uuid()).not.toBe(a);
    });
});

describe('settingsFrom', () => {
    it('starts a new quiz with self-paced defaults', () => {
        expect(settingsFrom(null)).toMatchObject({
            mode: 'self_paced',
            max_attempts: 1,
            negative_marking: false,
        });
    });

    it('keeps saved settings', () => {
        expect(
            settingsFrom({ max_attempts: 3, score_visibility: 'hidden' }),
        ).toMatchObject({ max_attempts: 3, score_visibility: 'hidden' });
    });
});

describe('copyFromBank', () => {
    it('copies a bank question with new ids for it and its options (DATA-008)', () => {
        const copy = copyFromBank(bankQuestion);

        expect(copy.id).toMatch(UUID);
        expect(copy.id).not.toBe(bankQuestion.id);
        expect(copy.prompt).toBe('Capital of Greece?');
        expect(copy.options.map((option) => option.text)).toEqual([
            'Athens',
            'Sparta',
        ]);
        expect(copy.options.map((option) => option.is_correct)).toEqual([
            true,
            false,
        ]);
        expect(copy.options.map((option) => option.id)).not.toContain('opt-1');
    });
});

describe('quizPayload', () => {
    it("sends the questions in order with ids and only their type's items", () => {
        const question = newQuestion();
        question.type = 'free_text';
        const copy = copyFromBank(bankQuestion);

        const payload = quizPayload([copy, question]);

        expect(payload.questions.map((item) => item.id)).toEqual([
            copy.id,
            question.id,
        ]);
        expect(payload.questions[1].options).toEqual([]);
        expect(payload.questions[0].options[0]).toEqual({
            id: copy.options[0].id,
            text: 'Athens',
            is_correct: true,
        });
        expect(
            Object.keys(payload.questions[0].options[0]).includes('key'),
        ).toBe(false);
    });

    it('keeps the ids stable between autosaves', () => {
        const question = ensureIds(newQuestion());

        expect(quizPayload([question])).toEqual(quizPayload([question]));
    });
});

describe('questionsFrom', () => {
    it('restores saved questions with list keys', () => {
        const restored = questionsFrom({
            questions: [{ ...bankQuestion, id: 'q-1' }],
        });

        expect(restored[0].id).toBe('q-1');
        expect(restored[0].options[0].key).toBeTruthy();
        expect(restored[0].options[0].id).toBe('opt-1');
    });
});

describe('questionErrors', () => {
    it('picks the errors of one question by field', () => {
        expect(
            questionErrors(
                {
                    'questions.0.prompt': 'Required',
                    'questions.1.options': 'Too few',
                    'questions.10.points': 'Too many',
                },
                1,
            ),
        ).toEqual({ options: 'Too few' });
    });
});

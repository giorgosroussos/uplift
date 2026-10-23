import { describe, expect, it } from 'vitest';
import {
    pollOptionsFrom,
    pollSettingsFrom,
    toggleChoice,
    votePercentages,
} from '@/lib/pollForm';

describe('pollSettingsFrom', () => {
    it('gives a new poll one choice and results after voting', () => {
        expect(pollSettingsFrom(null)).toEqual({
            max_choices: 1,
            results_visibility: 'after_vote',
        });
    });

    it('keeps the saved settings', () => {
        expect(
            pollSettingsFrom({
                max_choices: 3,
                results_visibility: 'after_close',
            }),
        ).toEqual({ max_choices: 3, results_visibility: 'after_close' });
    });
});

describe('pollOptionsFrom', () => {
    it('starts a new poll with two empty options with distinct ids', () => {
        const options = pollOptionsFrom(null);

        expect(options).toHaveLength(2);
        expect(options.every((option) => option.label === '')).toBe(true);
        expect(options[0].id).not.toBe(options[1].id);
    });

    it('keeps the saved options and their ids', () => {
        const saved = [
            { id: 'a', label: 'Yes' },
            { id: 'b', label: 'No' },
        ];

        expect(pollOptionsFrom({ options: saved })).toEqual(saved);
    });
});

describe('toggleChoice', () => {
    it('replaces the choice when only one option may be chosen', () => {
        expect(toggleChoice(['a'], 'b', 1)).toEqual(['b']);
    });

    // UC-013: a poll allowing at most 2 options does not take a third.
    it('does not add an option beyond the limit', () => {
        expect(toggleChoice(['a', 'b'], 'c', 2)).toEqual(['a', 'b']);
    });

    it('removes a chosen option', () => {
        expect(toggleChoice(['a', 'b'], 'a', 2)).toEqual(['b']);
    });
});

describe('votePercentages', () => {
    it('shares the votes per option', () => {
        expect(
            votePercentages({
                participants: 4,
                options: [
                    { id: 'a', label: 'A', votes: 3 },
                    { id: 'b', label: 'B', votes: 1 },
                ],
            }),
        ).toEqual({ a: 75, b: 25 });
    });

    it('gives 0 when nobody has voted', () => {
        expect(
            votePercentages({
                participants: 0,
                options: [{ id: 'a', label: 'A', votes: 0 }],
            }),
        ).toEqual({ a: 0 });
    });
});

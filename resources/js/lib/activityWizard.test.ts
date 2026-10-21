import { describe, expect, it } from 'vitest';
import {
    fromLocalInput,
    problemErrors,
    readyForReview,
    toLocalInput,
} from './activityWizard';

describe('activity wizard helpers', () => {
    it('takes the first message of each field from problem details', () => {
        expect(
            problemErrors({
                type: 'urn:uplift:problem:validation',
                title: 'Invalid',
                status: 422,
                detail: 'The title field is required.',
                errors: { title: ['Required', 'Too short'], mode: [] },
            }),
        ).toEqual({ title: 'Required' });
        expect(problemErrors(null)).toEqual({});
    });

    it('round-trips deadlines between UTC and datetime-local values', () => {
        const local = '2026-11-02T09:30';
        const iso = fromLocalInput(local);

        expect(iso).not.toBeNull();
        expect(iso?.endsWith('Z')).toBe(true);
        expect(toLocalInput(iso)).toBe(local);
        expect(fromLocalInput('')).toBeNull();
        expect(toLocalInput(null)).toBe('');
        expect(toLocalInput('not a date')).toBe('');
    });

    it('is ready for review once every step before it is complete', () => {
        expect(
            readyForReview([
                { key: 'basics', component: null, complete: true },
                { key: 'settings', component: 'X', complete: true },
                { key: 'review', component: null, complete: false },
            ]),
        ).toBe(true);
        expect(
            readyForReview([
                { key: 'basics', component: null, complete: true },
                { key: 'settings', component: 'X', complete: false },
                { key: 'review', component: null, complete: false },
            ]),
        ).toBe(false);
    });
});

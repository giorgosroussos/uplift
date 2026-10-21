import { describe, expect, it } from 'vitest';
import { gradeLabel, passLabel } from '@/lib/grade';
import type { Grade } from '@/types/attempts';

const strings: Record<string, string> = {
    'assessment.grade.scale_0_10': ':value / 10',
    'assessment.grade.scale_0_100': ':value / 100',
    'assessment.grade.points_pass_mark': ':earned / :max points',
    'assessment.grade.passed': 'Passed',
    'assessment.grade.failed': 'Not passed',
};

function t(key: string, replacements: Record<string, string> = {}): string {
    return Object.entries(replacements).reduce(
        (text, [name, value]) => text.replace(`:${name}`, value),
        strings[key] ?? key,
    );
}

function grade(overrides: Partial<Grade>): Grade {
    return {
        scale: 'scale_0_10',
        value: '8.8',
        percentage: '87.5',
        earned: '7.00',
        max: '8.00',
        passed: null,
        ...overrides,
    };
}

describe('gradeLabel', () => {
    it('UC-017: Given a 0-10 scale and a score of 7 out of 8 points, Then the result shows 8.8', () => {
        expect(gradeLabel(grade({}), t)).toBe('8.8 / 10');
    });

    it('shows the 0-100 grade and the points of the points scale', () => {
        expect(
            gradeLabel(grade({ scale: 'scale_0_100', value: '87.5' }), t),
        ).toBe('87.5 / 100');
        expect(
            gradeLabel(grade({ scale: 'points_pass_mark', value: '7.00' }), t),
        ).toBe('7.00 / 8.00 points');
    });

    it('falls back to points when there is nothing to convert', () => {
        expect(
            gradeLabel(grade({ value: null, earned: '0.00', max: '0.00' }), t),
        ).toBe('0.00 / 0.00 points');
    });
});

describe('passLabel', () => {
    it('shows passed or not passed only with a pass mark', () => {
        expect(passLabel(grade({ passed: true }), t)).toBe('Passed');
        expect(passLabel(grade({ passed: false }), t)).toBe('Not passed');
        expect(passLabel(grade({ passed: null }), t)).toBeNull();
    });
});

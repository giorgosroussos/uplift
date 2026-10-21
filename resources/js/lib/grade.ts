import type { Grade } from '@/types/attempts';

type Translate = (key: string, replacements?: Record<string, string>) => string;

/**
 * The grade as shown to people (UC-017): "8.8 / 10", "87.5 / 100" or
 * "7.00 / 8.00 points". The value is converted and rounded on the server
 * (DATA-003, DATA-005); the page only formats it.
 */
export function gradeLabel(grade: Grade, t: Translate): string {
    if (grade.scale === 'points_pass_mark' || grade.value === null) {
        return t('assessment.grade.points_pass_mark', {
            earned: grade.earned,
            max: grade.max,
        });
    }

    return t(`assessment.grade.${grade.scale}`, { value: grade.value });
}

/**
 * "Passed" / "Not passed" against the quiz's pass mark, or null when the
 * quiz has none.
 */
export function passLabel(grade: Grade, t: Translate): string | null {
    if (grade.passed === null) {
        return null;
    }

    return t(
        grade.passed ? 'assessment.grade.passed' : 'assessment.grade.failed',
    );
}

/**
 * Helpers of the activity wizard (UC-022): autosave over the JSON
 * endpoints, whose errors are RFC 9457 problem details, and conversion of
 * deadlines between UTC ISO strings and <input type="datetime-local">.
 */
import type { StepErrors, WizardStep } from '@/types/activities';

export type ProblemDetails = {
    type: string;
    title: string;
    status: number;
    detail: string;
    errors?: Record<string, string[]>;
};

export type SaveResult<T> =
    | { ok: true; body: T }
    | { ok: false; status: number; problem: ProblemDetails | null };

/**
 * First message per field of a problem's validation errors.
 */
export function problemErrors(problem: ProblemDetails | null): StepErrors {
    const errors: StepErrors = {};

    for (const [field, messages] of Object.entries(problem?.errors ?? {})) {
        if (messages.length > 0) {
            errors[field] = messages[0];
        }
    }

    return errors;
}

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Sends one autosave request; never throws for HTTP errors.
 */
export async function sendJson<T>(
    method: 'POST' | 'PUT',
    url: string,
    data: unknown,
): Promise<SaveResult<T>> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = xsrfToken();

    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    try {
        const response = await fetch(url, {
            method,
            headers,
            body: JSON.stringify(data),
            credentials: 'same-origin',
        });

        if (response.ok) {
            return { ok: true, body: (await response.json()) as T };
        }

        let problem: ProblemDetails | null = null;

        try {
            problem = (await response.json()) as ProblemDetails;
        } catch {
            problem = null;
        }

        return { ok: false, status: response.status, problem };
    } catch {
        return { ok: false, status: 0, problem: null };
    }
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * A UTC ISO timestamp as the value of a datetime-local input, in the
 * browser's time zone.
 */
export function toLocalInput(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * The value of a datetime-local input (browser time zone) as a UTC ISO
 * timestamp, or null when empty.
 */
export function fromLocalInput(local: string): string | null {
    if (!local) {
        return null;
    }

    const date = new Date(local);

    return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

/**
 * Whether every step before the review is complete.
 */
export function readyForReview(steps: WizardStep[]): boolean {
    return steps
        .filter((step) => step.key !== 'review')
        .every((step) => step.complete);
}

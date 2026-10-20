import { router } from '@inertiajs/vue3';
import type { Organization } from '@/types';

/*
 * DATA-011: the organisation's accent colour is the app's primary colour.
 * Without an accent colour the theme's own primary colour applies.
 */
const ACCENT = /^#[0-9a-f]{6}$/i;

const VARIABLES = {
    '--primary': 'color',
    '--primary-foreground': 'foreground',
    '--sidebar-primary': 'color',
    '--sidebar-primary-foreground': 'foreground',
    '--ring': 'color',
} as const;

function channel(value: number): number {
    const c = value / 255;

    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

/**
 * Black or white, whichever contrasts more with the colour (WCAG luminance).
 */
export function foregroundFor(color: string): '#000000' | '#ffffff' {
    const [r, g, b] = [1, 3, 5].map((i) =>
        channel(parseInt(color.slice(i, i + 2), 16)),
    );
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;

    return (luminance + 0.05) / 0.05 > 1.05 / (luminance + 0.05)
        ? '#000000'
        : '#ffffff';
}

export function applyAccentColor(
    color: string | null | undefined,
    root: HTMLElement = document.documentElement,
): void {
    const valid = typeof color === 'string' && ACCENT.test(color);

    for (const [variable, kind] of Object.entries(VARIABLES)) {
        if (!valid) {
            root.style.removeProperty(variable);
        } else {
            root.style.setProperty(
                variable,
                kind === 'color' ? color : foregroundFor(color),
            );
        }
    }
}

function applyFrom(page: { props: Record<string, unknown> }): void {
    const organization = page.props.organization as
        Organization | null | undefined;

    applyAccentColor(organization?.accent_color);
}

/**
 * Applies the accent colour on the first page (navigate also fires on first
 * load), after every visit and after every successful form submission, so a
 * saved change shows at once.
 */
export function initializeOrganizationTheme(): void {
    router.on('navigate', (event) => applyFrom(event.detail.page));
    router.on('success', (event) => applyFrom(event.detail.page));
}

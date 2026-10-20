import { describe, expect, it } from 'vitest';
import { applyAccentColor, foregroundFor } from '@/lib/organizationTheme';

describe('organisation accent colour (DATA-011)', () => {
    it('sets the accent colour as the primary colour', () => {
        const root = document.createElement('div');

        applyAccentColor('#1A2B3C', root);

        expect(root.style.getPropertyValue('--primary')).toBe('#1A2B3C');
        expect(root.style.getPropertyValue('--sidebar-primary')).toBe(
            '#1A2B3C',
        );
        expect(root.style.getPropertyValue('--primary-foreground')).toBe(
            '#ffffff',
        );
    });

    it('falls back to the theme colour without a valid accent colour', () => {
        const root = document.createElement('div');
        applyAccentColor('#1A2B3C', root);

        for (const color of [null, undefined, '', 'blue', '#12345']) {
            applyAccentColor(color, root);
            expect(root.style.getPropertyValue('--primary')).toBe('');
            expect(root.style.getPropertyValue('--primary-foreground')).toBe(
                '',
            );
        }
    });

    it('picks a readable foreground', () => {
        expect(foregroundFor('#FFFF00')).toBe('#000000');
        expect(foregroundFor('#FFFFFF')).toBe('#000000');
        expect(foregroundFor('#000080')).toBe('#ffffff');
        expect(foregroundFor('#1A2B3C')).toBe('#ffffff');
    });
});

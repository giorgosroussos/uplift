import { describe, expect, it } from 'vitest';
import { avatarNumber, avatarUrl } from '@/lib/avatars';

describe('preset avatars (DATA-014)', () => {
    it('maps the keys avatar-01 to avatar-12 to the bundled SVGs', () => {
        expect(avatarUrl('avatar-01')).toBe('/avatars/avatar-01.svg');
        expect(avatarUrl('avatar-12')).toBe('/avatars/avatar-12.svg');
        expect(avatarNumber('avatar-03')).toBe(3);
    });

    it('has no image for an empty or unknown key', () => {
        expect(avatarUrl(null)).toBeNull();
        expect(avatarUrl('')).toBeNull();
        expect(avatarUrl('avatar-13')).toBeNull();
        expect(avatarUrl('https://example.com/me.png')).toBeNull();
    });
});

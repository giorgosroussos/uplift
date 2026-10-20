/**
 * The preset avatars bundled with the app (DATA-014): keys avatar-01 to
 * avatar-12, served from public/avatars. Without a choice the UI shows the
 * user's initials.
 */
export const AVATAR_PATTERN = /^avatar-(0[1-9]|1[0-2])$/;

export function avatarUrl(key: string | null | undefined): string | null {
    return key && AVATAR_PATTERN.test(key) ? `/avatars/${key}.svg` : null;
}

export function avatarNumber(key: string): number {
    return Number(key.slice('avatar-'.length));
}

import { describe, expect, it } from 'vitest';
import { groupByDay } from '@/lib/notificationGroups';

describe('notification centre grouping by day (UC-007)', () => {
    const now = new Date(2026, 9, 20, 15, 0, 0);
    const at = (day: number, hour: number) =>
        new Date(2026, 9, day, hour, 0, 0).toISOString();

    it('groups newest-first entries into today, yesterday and earlier days', () => {
        const items = [
            { id: 'a', created_at: at(20, 14) },
            { id: 'b', created_at: at(20, 9) },
            { id: 'c', created_at: at(19, 23) },
            { id: 'd', created_at: at(17, 8) },
        ];

        const groups = groupByDay(items, now);

        expect(groups.map((group) => group.relative)).toEqual([
            'today',
            'yesterday',
            null,
        ]);
        expect(groups.map((group) => group.day)).toEqual([
            '2026-10-20',
            '2026-10-19',
            '2026-10-17',
        ]);
        expect(groups[0].items.map((item) => item.id)).toEqual(['a', 'b']);
        expect(groups[2].items.map((item) => item.id)).toEqual(['d']);
    });

    it('returns no groups without notifications', () => {
        expect(groupByDay([], now)).toEqual([]);
    });
});

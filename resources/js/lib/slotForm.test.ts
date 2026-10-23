import { describe, expect, it } from 'vitest';
import {
    addMinutes,
    blankSlot,
    canAct,
    defaultGroup,
    durationFrom,
    slotsFrom,
    slotsPayload,
    withEnd,
    withStart,
} from '@/lib/slotForm';
import type { SlotGroup } from '@/types/slots';

function group(overrides: Partial<SlotGroup>): SlotGroup {
    return {
        id: 'g',
        name: 'Group',
        members: 2,
        slot_id: null,
        too_large: false,
        in_other_group: false,
        ...overrides,
    };
}

describe('slot form', () => {
    it('starts a new slot booking with one empty slot of one place', () => {
        const slots = slotsFrom(null);

        expect(slots).toHaveLength(1);
        expect(slots[0].starts_at).toBe('');
        expect(slots[0].capacity).toBe(1);
        expect(slots[0].endTouched).toBe(false);
        expect(slots[0].id).toMatch(/^[0-9a-f-]{36}$/);
        expect(blankSlot().id).not.toBe(blankSlot().id);
    });

    it('pre-fills the end of a new slot from the slot length: 10:00 with 30 minutes ends at 10:30', () => {
        const slot = withStart(blankSlot(), '2026-11-03T10:00', 30);

        expect(slot.starts_at).toBe('2026-11-03T10:00');
        expect(slot.ends_at).toBe('2026-11-03T10:30');
    });

    it('lets the teacher change the pre-filled end, and then keeps it', () => {
        const prefilled = withStart(blankSlot(), '2026-11-03T10:00', 30);
        const changed = withEnd(prefilled, '2026-11-03T10:45');

        expect(changed.ends_at).toBe('2026-11-03T10:45');
        expect(withStart(changed, '2026-11-03T10:15', 30).ends_at).toBe(
            '2026-11-03T10:45',
        );
    });

    it('fills nothing without a slot length', () => {
        expect(withStart(blankSlot(), '2026-11-03T10:00', null).ends_at).toBe(
            '',
        );
    });

    it('adds minutes across midnight', () => {
        expect(addMinutes('2026-11-03T23:45', 30)).toBe('2026-11-04T00:15');
        expect(addMinutes('', 30)).toBe('');
    });

    it('restores saved slots without changing their end', () => {
        const saved = {
            slot_duration: 1800,
            slots: [
                {
                    id: 'a',
                    starts_at: '2026-11-03T10:00:00Z',
                    ends_at: '2026-11-03T10:45:00Z',
                    location: 'Room 1',
                    capacity: 2,
                },
            ],
        };
        const slots = slotsFrom(saved);

        expect(durationFrom(saved)).toBe(30);
        expect(slots[0].endTouched).toBe(true);
        expect(slots[0].location).toBe('Room 1');
        expect(new Date(slots[0].ends_at).toISOString()).toBe(
            '2026-11-03T10:45:00.000Z',
        );
    });

    it('sends times in ISO 8601, an empty location as none and the length in seconds', () => {
        const slot = {
            ...withStart(blankSlot(), '2026-11-03T10:00', 30),
            location: '  ',
            capacity: '3' as unknown as number,
        };
        const payload = slotsPayload([slot], 30);

        expect(payload.slot_duration).toBe(1800);
        expect(payload.slots[0].location).toBeNull();
        expect(payload.slots[0].capacity).toBe(3);
        expect(payload.slots[0].starts_at).toBe(
            new Date('2026-11-03T10:00').toISOString(),
        );
        expect(payload.slots[0].ends_at).toBe(
            new Date('2026-11-03T10:30').toISOString(),
        );
        expect(slotsPayload([slot], null).slot_duration).toBeNull();
    });
});

describe('acting group', () => {
    it('prefers the group that already holds a booking', () => {
        expect(
            defaultGroup([
                group({ id: 'g1' }),
                group({ id: 'g2', slot_id: 's' }),
            ]),
        ).toBe('g2');
    });

    it('skips groups that may not act', () => {
        const groups = [
            group({ id: 'g1', too_large: true }),
            group({ id: 'g2', in_other_group: true }),
            group({ id: 'g3' }),
        ];

        expect(canAct(groups[0])).toBe(false);
        expect(canAct(groups[1])).toBe(false);
        expect(defaultGroup(groups)).toBe('g3');
        expect(defaultGroup([])).toBeNull();
    });
});

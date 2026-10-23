import { fromLocalInput, toLocalInput } from '@/lib/activityWizard';
import { uuid } from '@/lib/quizForm';
import type { StepData } from '@/types/activities';
import type { SlotGroup, SlotInput, SlotPayload } from '@/types/slots';

/*
 * State of the slot step of the activity wizard and of the slot booking
 * page (UC-016, SCOPE-028). Every slot carries a UUID made here for new
 * ones, so autosaving the same list again changes nothing on the server.
 * The slot length only fills in the end of new slots from their start
 * until the teacher sets the end by hand; it is never enforced.
 */

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * The datetime-local value `minutes` after the given one, or '' when the
 * start is empty or invalid.
 */
export function addMinutes(local: string, minutes: number): string {
    if (!local) {
        return '';
    }

    const date = new Date(local);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    date.setMinutes(date.getMinutes() + minutes);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function blankSlot(): SlotInput {
    return {
        id: uuid(),
        starts_at: '',
        ends_at: '',
        location: '',
        capacity: 1,
        endTouched: false,
    };
}

/**
 * The slot length in minutes from the saved step (stored in seconds).
 */
export function durationFrom(data: StepData | null): number | null {
    const seconds = data?.slot_duration;

    return typeof seconds === 'number' && seconds > 0
        ? Math.round(seconds / 60)
        : null;
}

/**
 * The saved slots, or one empty slot for a new slot booking. Saved slots
 * keep their end: the slot length does not change them.
 */
export function slotsFrom(data: StepData | null): SlotInput[] {
    const saved = (data?.slots ?? null) as
        | {
              id: string;
              starts_at: string;
              ends_at: string;
              location: string | null;
              capacity: number;
          }[]
        | null;

    if (saved && saved.length > 0) {
        return saved.map((slot) => ({
            id: slot.id,
            starts_at: toLocalInput(slot.starts_at),
            ends_at: toLocalInput(slot.ends_at),
            location: slot.location ?? '',
            capacity: slot.capacity,
            endTouched: true,
        }));
    }

    return [blankSlot()];
}

/**
 * The slot after its start changed: unless its end was set by hand, the
 * end becomes start + slot length (when there is a slot length).
 */
export function withStart(
    slot: SlotInput,
    startsAt: string,
    minutes: number | null,
): SlotInput {
    const next = { ...slot, starts_at: startsAt };

    if (!slot.endTouched && minutes !== null && minutes > 0) {
        next.ends_at = addMinutes(startsAt, minutes);
    }

    return next;
}

/**
 * The slot after the teacher set its end by hand.
 */
export function withEnd(slot: SlotInput, endsAt: string): SlotInput {
    return { ...slot, ends_at: endsAt, endTouched: true };
}

/**
 * The step as the server expects it: times in ISO 8601 (UTC), an empty
 * location as none, the slot length in seconds.
 */
export function slotsPayload(
    slots: SlotInput[],
    minutes: number | null,
): { slot_duration: number | null; slots: SlotPayload[] } {
    return {
        slot_duration:
            minutes === null || Number.isNaN(Number(minutes))
                ? null
                : Math.round(Number(minutes) * 60),
        slots: slots.map((slot) => ({
            id: slot.id,
            starts_at: fromLocalInput(slot.starts_at),
            ends_at: fromLocalInput(slot.ends_at),
            location: slot.location.trim() === '' ? null : slot.location,
            capacity: Number(slot.capacity),
        })),
    };
}

/**
 * Whether a group may act in the activity at all (SCOPE-025).
 */
export function canAct(group: SlotGroup): boolean {
    return !group.too_large && !group.in_other_group;
}

/**
 * The group the student books for by default: the one that already holds
 * a booking, else the first that may act, else none.
 */
export function defaultGroup(groups: SlotGroup[]): string | null {
    const holding = groups.find((group) => group.slot_id !== null);

    if (holding) {
        return holding.id;
    }

    return groups.find(canAct)?.id ?? null;
}

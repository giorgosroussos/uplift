import type { ActivityStatus } from '@/types/activities';

/** 1-100 slots, places 1-1000 (SaveSlots on the server). */
export const MIN_SLOTS = 1;
export const MAX_SLOTS = 100;
export const MAX_SLOT_CAPACITY = 1000;

/** Slot length in minutes: 5 minutes to 24 hours (stored in seconds). */
export const MIN_SLOT_MINUTES = 5;
export const MAX_SLOT_MINUTES = 1440;

/** One slot as the content step edits it; times as datetime-local values. */
export type SlotInput = {
    id: string;
    starts_at: string;
    ends_at: string;
    location: string;
    capacity: number;
    /** Whether the end was set by hand, so the slot length no longer fills it. */
    endTouched: boolean;
};

/** One slot as saved and sent: times as ISO 8601. */
export type SlotPayload = {
    id: string;
    starts_at: string | null;
    ends_at: string | null;
    location: string | null;
    capacity: number;
};

/** A slot as the slot booking page shows it (ShowSlotBooking). */
export type SlotRow = {
    id: string;
    starts_at: string;
    ends_at: string;
    location: string | null;
    capacity: number;
    remaining: number;
    started: boolean;
    /** Only for the owning teacher (OWN-010). */
    holders?: string[];
};

/** The slot booking as its page shows it. */
export type SlotBookingDetails = {
    activity_id: string;
    title: string;
    description: string | null;
    status: ActivityStatus;
    course_space_title: string;
    deadline_at: string | null;
    is_group: boolean;
    group_size: number | null;
    is_open: boolean;
    slots: SlotRow[];
};

/** A group the student may book for, with its current booking (SCOPE-025). */
export type SlotGroup = {
    id: string;
    name: string;
    members: number;
    slot_id: string | null;
    too_large: boolean;
    in_other_group: boolean;
};

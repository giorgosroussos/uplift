import type { ActivityStatus } from '@/types/activities';

export const POLL_RESULTS_VISIBILITIES = ['after_vote', 'after_close'] as const;

export type PollResultsVisibility = (typeof POLL_RESULTS_VISIBILITIES)[number];

/** Between 2 and 20 options (SavePollOptions on the server). */
export const MIN_POLL_OPTIONS = 2;
export const MAX_POLL_OPTIONS = 20;

/** The settings step of a poll (UC-013, SCOPE-013). */
export type PollSettings = {
    max_choices: number;
    results_visibility: PollResultsVisibility;
};

/** One option of the content step as saved and sent. */
export type PollOptionInput = {
    id: string;
    label: string;
};

/** The poll as its page shows it (ShowPoll on the server). */
export type PollDetails = {
    activity_id: string;
    title: string;
    description: string | null;
    status: ActivityStatus;
    course_space_title: string;
    deadline_at: string | null;
    max_choices: number;
    results_visibility: PollResultsVisibility;
    is_open: boolean;
    options: PollOptionInput[];
};

/** Counts only: never who voted or what anyone voted (SEC-009). */
export type PollResults = {
    participants: number;
    options: { id: string; label: string; votes: number }[];
};

import { uuid } from '@/lib/quizForm';
import type { StepData } from '@/types/activities';
import type { PollOptionInput, PollResults, PollSettings } from '@/types/polls';
import { MIN_POLL_OPTIONS } from '@/types/polls';

/*
 * State of the poll steps of the activity wizard and of the voting form
 * (UC-013). Every option carries a UUID made here for new ones, so
 * autosaving the same list again changes nothing on the server.
 */

export function defaultPollSettings(): PollSettings {
    return { max_choices: 1, results_visibility: 'after_vote' };
}

/**
 * The saved settings, completed with the defaults for a new poll.
 */
export function pollSettingsFrom(data: StepData | null): PollSettings {
    return {
        ...defaultPollSettings(),
        ...((data ?? {}) as Partial<PollSettings>),
    };
}

export function blankPollOption(): PollOptionInput {
    return { id: uuid(), label: '' };
}

/**
 * The saved options, or the minimum number of empty ones for a new poll.
 */
export function pollOptionsFrom(data: StepData | null): PollOptionInput[] {
    const saved = (data?.options ?? null) as PollOptionInput[] | null;

    if (saved && saved.length > 0) {
        return saved.map((option) => ({ id: option.id, label: option.label }));
    }

    return Array.from({ length: MIN_POLL_OPTIONS }, blankPollOption);
}

/**
 * Toggles an option of the voting form. With one choice the new option
 * replaces the old one; with more, an option beyond the limit is not added.
 */
export function toggleChoice(
    chosen: string[],
    id: string,
    maxChoices: number,
): string[] {
    if (chosen.includes(id)) {
        return chosen.filter((value) => value !== id);
    }

    if (maxChoices <= 1) {
        return [id];
    }

    return chosen.length >= maxChoices ? chosen : [...chosen, id];
}

/**
 * The share of the votes of each option, as a whole percentage of all
 * votes (0 when nobody has voted).
 */
export function votePercentages(results: PollResults): Record<string, number> {
    const total = results.options.reduce(
        (sum, option) => sum + option.votes,
        0,
    );

    return Object.fromEntries(
        results.options.map((option) => [
            option.id,
            total === 0 ? 0 : Math.round((option.votes / total) * 100),
        ]),
    );
}

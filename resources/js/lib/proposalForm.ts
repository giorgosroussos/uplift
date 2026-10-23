import type {
    ProposalGroup,
    ProposalRow,
    TopicProposalStatus,
} from '@/types/proposals';

/*
 * State of the topic proposal page (UC-015, UC-027): which group the
 * student proposes for and what the latest proposal says.
 */

/**
 * Whether a group may take part in the activity at all (SCOPE-025).
 */
export function canActFor(group: ProposalGroup): boolean {
    return !group.too_large && !group.in_other_group;
}

/**
 * The group the student proposes for by default: the one that already
 * has a proposal, else the first that may act, else none.
 */
export function defaultProposalGroup(groups: ProposalGroup[]): string | null {
    const proposing = groups.find((group) => group.proposals.length > 0);

    if (proposing) {
        return proposing.id;
    }

    return groups.find(canActFor)?.id ?? null;
}

/**
 * The status of the newest proposal (the server sends newest first), or
 * null when there is none.
 */
export function latestStatus(
    proposals: ProposalRow[],
): TopicProposalStatus | null {
    return proposals[0]?.status ?? null;
}

/**
 * The proposal as the server expects it: an empty description is none.
 */
export function proposalPayload(
    title: string,
    description: string,
    groupId: string | null,
): Record<string, string | null> {
    const payload: Record<string, string | null> = {
        title: title.trim(),
        description: description.trim() === '' ? null : description,
    };

    if (groupId !== null) {
        payload.group_id = groupId;
    }

    return payload;
}

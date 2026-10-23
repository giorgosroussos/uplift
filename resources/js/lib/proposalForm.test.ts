import { describe, expect, it } from 'vitest';
import {
    canActFor,
    defaultProposalGroup,
    latestStatus,
    proposalPayload,
} from '@/lib/proposalForm';
import type { ProposalGroup, ProposalRow } from '@/types/proposals';

function row(overrides: Partial<ProposalRow>): ProposalRow {
    return {
        id: 'p',
        title: 'Topic',
        description: null,
        status: 'pending',
        teacher_comment: null,
        created_at: null,
        decided_at: null,
        ...overrides,
    };
}

function group(overrides: Partial<ProposalGroup>): ProposalGroup {
    return {
        id: 'g',
        name: 'Group',
        members: 2,
        too_large: false,
        in_other_group: false,
        proposals: [],
        may_propose: true,
        ...overrides,
    };
}

describe('proposal form', () => {
    it('lets only groups within the size and without members elsewhere act', () => {
        expect(canActFor(group({}))).toBe(true);
        expect(canActFor(group({ too_large: true }))).toBe(false);
        expect(canActFor(group({ in_other_group: true }))).toBe(false);
    });

    it('defaults to the group that already proposed, else the first that may act', () => {
        expect(
            defaultProposalGroup([
                group({ id: 'a' }),
                group({ id: 'b', proposals: [row({})] }),
            ]),
        ).toBe('b');
        expect(
            defaultProposalGroup([
                group({ id: 'a', too_large: true }),
                group({ id: 'b' }),
            ]),
        ).toBe('b');
        expect(defaultProposalGroup([group({ in_other_group: true })])).toBe(
            null,
        );
        expect(defaultProposalGroup([])).toBe(null);
    });

    it('reads the status of the newest proposal', () => {
        expect(latestStatus([])).toBe(null);
        expect(
            latestStatus([
                row({ status: 'pending' }),
                row({ status: 'rejected' }),
            ]),
        ).toBe('pending');
    });

    it('sends an empty description as none and the group only when acting for one', () => {
        expect(proposalPayload(' Graphs ', '  ', null)).toEqual({
            title: 'Graphs',
            description: null,
        });
        expect(proposalPayload('Graphs', 'Details', 'g1')).toEqual({
            title: 'Graphs',
            description: 'Details',
            group_id: 'g1',
        });
    });
});

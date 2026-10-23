import type { ActivityStatus } from '@/types/activities';

/** ProposeTopic on the server: title 1-255, description up to 5000. */
export const MAX_PROPOSAL_TITLE = 255;
export const MAX_PROPOSAL_DESCRIPTION = 5000;

export type TopicProposalStatus = 'pending' | 'approved' | 'rejected';

/** One proposal as its proposer (or their group) sees it. */
export type ProposalRow = {
    id: string;
    title: string;
    description: string | null;
    status: TopicProposalStatus;
    teacher_comment: string | null;
    created_at: string | null;
    decided_at: string | null;
};

/** One proposal as the owning teacher sees it (OWN-009). */
export type ReviewedProposal = ProposalRow & {
    proposer: string;
    is_group: boolean;
};

/** The student's own proposals in an individual activity. */
export type OwnProposals = {
    proposals: ProposalRow[];
    may_propose: boolean;
};

/** A group the student may propose for, with its proposals (SCOPE-025). */
export type ProposalGroup = {
    id: string;
    name: string;
    members: number;
    too_large: boolean;
    in_other_group: boolean;
    proposals: ProposalRow[];
    may_propose: boolean;
};

/** The topic proposal activity as its page shows it. */
export type TopicProposalDetails = {
    activity_id: string;
    title: string;
    description: string | null;
    status: ActivityStatus;
    course_space_title: string;
    deadline_at: string | null;
    is_group: boolean;
    group_size: number | null;
    is_open: boolean;
};

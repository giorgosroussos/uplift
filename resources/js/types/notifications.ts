export type NotificationKind =
    | 'announcement_published'
    | 'activity_published'
    | 'grade_finalised'
    | 'grade_changed'
    | 'topic_proposal_decided'
    | 'group_invitation';

export type AnnouncementPublishedData = {
    course_space_id: string;
    announcement_id: string;
    course_space_title: string;
    announcement_title: string;
};

export type ActivityPublishedData = {
    course_space_id: string;
    activity_id: string;
    course_space_title: string;
    activity_title: string;
};

export type GradeFinalisedData = ActivityPublishedData & {
    attempt_id: string;
};

export type TopicProposalDecidedData = ActivityPublishedData & {
    topic_proposal_id: string;
    status: 'approved' | 'rejected';
};

export type GroupInvitationData = {
    course_space_id: string;
    group_id: string;
    group_membership_id: string;
    course_space_title: string;
    group_name: string;
};

type Entry<K extends NotificationKind, D> = {
    id: string;
    kind: K;
    data: D;
    read: boolean;
    created_at: string;
};

/**
 * One entry of the notification centre (UC-007, DEC-002).
 */
export type NotificationEntry =
    | Entry<'announcement_published', AnnouncementPublishedData>
    | Entry<'activity_published', ActivityPublishedData>
    | Entry<'grade_finalised', GradeFinalisedData>
    | Entry<'grade_changed', GradeFinalisedData>
    | Entry<'topic_proposal_decided', TopicProposalDecidedData>
    | Entry<'group_invitation', GroupInvitationData>;

/**
 * The shared prop `notifications`; null for guests.
 */
export type SharedNotifications = {
    unread_count: number;
};

export type NotificationKind =
    'announcement_published' | 'activity_published' | 'grade_finalised';

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
    | Entry<'grade_finalised', GradeFinalisedData>;

/**
 * The shared prop `notifications`; null for guests.
 */
export type SharedNotifications = {
    unread_count: number;
};

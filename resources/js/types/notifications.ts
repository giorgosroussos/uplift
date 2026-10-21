export type NotificationKind = 'announcement_published';

export type AnnouncementPublishedData = {
    course_space_id: string;
    announcement_id: string;
    course_space_title: string;
    announcement_title: string;
};

/**
 * One entry of the notification centre (UC-007, DEC-002).
 */
export type NotificationEntry = {
    id: string;
    kind: NotificationKind;
    data: AnnouncementPublishedData;
    read: boolean;
    created_at: string;
};

/**
 * The shared prop `notifications`; null for guests.
 */
export type SharedNotifications = {
    unread_count: number;
};

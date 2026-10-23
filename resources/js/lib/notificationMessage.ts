import type { NotificationEntry } from '@/types/notifications';

/**
 * The translation key and replacements of a notification centre entry
 * (UC-007, DEC-002): `notifications.centre.kinds.<kind>`, for a topic
 * decision `notifications.centre.kinds.topic_proposal_decided.<status>`.
 */
export function notificationMessage(notification: NotificationEntry): {
    key: string;
    params: Record<string, string>;
} {
    const base = `notifications.centre.kinds.${notification.kind}`;

    switch (notification.kind) {
        case 'announcement_published':
            return {
                key: base,
                params: {
                    space: notification.data.course_space_title,
                    title: notification.data.announcement_title,
                },
            };
        case 'topic_proposal_decided':
            return {
                key: `${base}.${notification.data.status}`,
                params: {
                    space: notification.data.course_space_title,
                    title: notification.data.activity_title,
                },
            };
        case 'group_invitation':
            return {
                key: base,
                params: {
                    space: notification.data.course_space_title,
                    group: notification.data.group_name,
                },
            };
        default:
            return {
                key: base,
                params: {
                    space: notification.data.course_space_title,
                    title: notification.data.activity_title,
                },
            };
    }
}

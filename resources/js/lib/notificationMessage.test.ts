import { describe, expect, it } from 'vitest';
import { notificationMessage } from '@/lib/notificationMessage';
import type { NotificationEntry } from '@/types/notifications';

const entry = {
    id: 'n',
    read: false,
    created_at: '2026-10-20T10:00:00Z',
};

describe('notification centre message (UC-007)', () => {
    it('uses the decision of a topic proposal in the key', () => {
        const data = {
            course_space_id: 's',
            activity_id: 'a',
            topic_proposal_id: 'p',
            course_space_title: 'Algorithms',
            activity_title: 'Thesis topics',
        };

        for (const status of ['approved', 'rejected'] as const) {
            const notification: NotificationEntry = {
                ...entry,
                kind: 'topic_proposal_decided',
                data: { ...data, status },
            };

            expect(notificationMessage(notification)).toEqual({
                key: `notifications.centre.kinds.topic_proposal_decided.${status}`,
                params: { space: 'Algorithms', title: 'Thesis topics' },
            });
        }
    });

    it('names the group and space of a group invitation', () => {
        expect(
            notificationMessage({
                ...entry,
                kind: 'group_invitation',
                data: {
                    course_space_id: 's',
                    group_id: 'g',
                    group_membership_id: 'm',
                    course_space_title: 'Algorithms',
                    group_name: 'Team A',
                },
            }),
        ).toEqual({
            key: 'notifications.centre.kinds.group_invitation',
            params: { space: 'Algorithms', group: 'Team A' },
        });
    });

    it('keeps the title of announcements and activities', () => {
        expect(
            notificationMessage({
                ...entry,
                kind: 'announcement_published',
                data: {
                    course_space_id: 's',
                    announcement_id: 'x',
                    course_space_title: 'Algorithms',
                    announcement_title: 'Exam date',
                },
            }),
        ).toEqual({
            key: 'notifications.centre.kinds.announcement_published',
            params: { space: 'Algorithms', title: 'Exam date' },
        });

        expect(
            notificationMessage({
                ...entry,
                kind: 'grade_changed',
                data: {
                    course_space_id: 's',
                    activity_id: 'a',
                    attempt_id: 't',
                    course_space_title: 'Algorithms',
                    activity_title: 'Quiz 1',
                },
            }).key,
        ).toBe('notifications.centre.kinds.grade_changed');
    });
});

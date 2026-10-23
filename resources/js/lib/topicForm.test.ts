import { describe, expect, it } from 'vitest';
import {
    blankTopic,
    canAct,
    defaultGroup,
    topicsFrom,
    topicsPayload,
} from '@/lib/topicForm';
import type { TopicGroup } from '@/types/topics';

function group(overrides: Partial<TopicGroup>): TopicGroup {
    return {
        id: 'g',
        name: 'Group',
        members: 2,
        topic_id: null,
        too_large: false,
        in_other_group: false,
        ...overrides,
    };
}

describe('topic form', () => {
    it('starts a new topic selection with one empty topic of one place', () => {
        const topics = topicsFrom(null);

        expect(topics).toHaveLength(1);
        expect(topics[0].title).toBe('');
        expect(topics[0].capacity).toBe(1);
        expect(topics[0].id).toMatch(/^[0-9a-f-]{36}$/);
    });

    it('restores saved topics', () => {
        const saved = [
            { id: 'a', title: 'Graphs', description: null, capacity: 2 },
        ];

        expect(topicsFrom({ topics: saved })).toEqual(saved);
    });

    it('gives every new topic its own id', () => {
        expect(blankTopic().id).not.toBe(blankTopic().id);
    });

    it('sends an empty description as none and the places as a number', () => {
        expect(
            topicsPayload([
                {
                    id: 'a',
                    title: 'Graphs',
                    description: '  ',
                    capacity: '3' as unknown as number,
                },
            ]),
        ).toEqual([
            { id: 'a', title: 'Graphs', description: null, capacity: 3 },
        ]);
    });
});

describe('acting group', () => {
    it('prefers the group that already holds a topic', () => {
        expect(
            defaultGroup([
                group({ id: 'g1' }),
                group({ id: 'g2', topic_id: 't' }),
            ]),
        ).toBe('g2');
    });

    it('skips groups that may not act', () => {
        const groups = [
            group({ id: 'g1', too_large: true }),
            group({ id: 'g2', in_other_group: true }),
            group({ id: 'g3' }),
        ];

        expect(canAct(groups[0])).toBe(false);
        expect(canAct(groups[1])).toBe(false);
        expect(defaultGroup(groups)).toBe('g3');
    });

    it('has no default without groups', () => {
        expect(defaultGroup([])).toBeNull();
    });
});

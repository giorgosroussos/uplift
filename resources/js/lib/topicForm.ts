import { uuid } from '@/lib/quizForm';
import type { StepData } from '@/types/activities';
import type { TopicGroup, TopicInput } from '@/types/topics';

/*
 * State of the topic step of the activity wizard and of the topic
 * selection page (UC-014). Every topic carries a UUID made here for new
 * ones, so autosaving the same list again changes nothing on the server.
 */

export function blankTopic(): TopicInput {
    return { id: uuid(), title: '', description: null, capacity: 1 };
}

/**
 * The saved topics, or one empty topic for a new topic selection.
 */
export function topicsFrom(data: StepData | null): TopicInput[] {
    const saved = (data?.topics ?? null) as TopicInput[] | null;

    if (saved && saved.length > 0) {
        return saved.map((topic) => ({
            id: topic.id,
            title: topic.title,
            description: topic.description ?? null,
            capacity: topic.capacity,
        }));
    }

    return [blankTopic()];
}

/**
 * The topics as the server expects them: an empty description is none.
 */
export function topicsPayload(topics: TopicInput[]): TopicInput[] {
    return topics.map((topic) => ({
        id: topic.id,
        title: topic.title,
        description:
            topic.description === null || topic.description.trim() === ''
                ? null
                : topic.description,
        capacity: Number(topic.capacity),
    }));
}

/**
 * Whether a group may act in the activity at all (SCOPE-025).
 */
export function canAct(group: TopicGroup): boolean {
    return !group.too_large && !group.in_other_group;
}

/**
 * The group the student acts for by default: the one that already holds a
 * topic, else the first that may act, else none.
 */
export function defaultGroup(groups: TopicGroup[]): string | null {
    const holding = groups.find((group) => group.topic_id !== null);

    if (holding) {
        return holding.id;
    }

    return groups.find(canAct)?.id ?? null;
}

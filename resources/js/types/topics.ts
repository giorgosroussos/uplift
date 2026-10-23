import type { ActivityStatus } from '@/types/activities';

/** At least one topic, places between 1 and 1000 (SaveTopics on the server). */
export const MIN_TOPICS = 1;
export const MAX_TOPICS = 100;
export const MAX_TOPIC_CAPACITY = 1000;

/** One topic of the content step as saved and sent. */
export type TopicInput = {
    id: string;
    title: string;
    description: string | null;
    capacity: number;
};

/** A topic as the topic selection page shows it (ShowTopicSelection). */
export type TopicRow = {
    id: string;
    title: string;
    description: string | null;
    capacity: number;
    remaining: number;
    /** Only for the owning teacher (OWN-008). */
    holders?: string[];
};

/** The topic selection as its page shows it. */
export type TopicSelectionDetails = {
    activity_id: string;
    title: string;
    description: string | null;
    status: ActivityStatus;
    course_space_title: string;
    deadline_at: string | null;
    is_group: boolean;
    group_size: number | null;
    is_open: boolean;
    topics: TopicRow[];
};

/** A group the student may act for, with its current choice (SCOPE-025). */
export type TopicGroup = {
    id: string;
    name: string;
    members: number;
    topic_id: string | null;
    too_large: boolean;
    in_other_group: boolean;
};

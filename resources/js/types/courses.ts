export type CourseSpaceStatus = 'active' | 'archived';

export type EnrolmentPolicy = 'open' | 'key';

export type CourseSpaceSummary = {
    id: string;
    title: string;
    description: string | null;
    status: CourseSpaceStatus;
    enrolment_policy: EnrolmentPolicy;
    teacher: string;
    enrolments_count: number | null;
};

export type CatalogueEntry = {
    id: string;
    title: string;
    description: string | null;
    enrolment_policy: EnrolmentPolicy;
    teacher: string;
    enrolled: boolean;
};

export type CourseSpaceForm = {
    title: string;
    description: string;
    enrolment_policy: EnrolmentPolicy;
    enrolment_key: string;
};

export type CourseSpaceDetails = {
    id: string;
    title: string;
    description: string | null;
    status: CourseSpaceStatus;
    enrolment_policy: EnrolmentPolicy;
    enrolment_key: string | null;
    teacher: string;
};

export type EnrolledStudent = {
    enrolment_id: string;
    name: string;
    email: string;
    enrolled_at: string;
};

export type StudentOption = {
    id: string;
    name: string;
    email: string;
};

export type Announcement = {
    id: string;
    title: string;
    body: string;
    author: string;
    published_at: string | null;
};

export type AnnouncementForm = {
    title: string;
    body: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

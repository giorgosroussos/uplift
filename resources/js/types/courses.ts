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

export type GroupMembershipStatus =
    'invited' | 'member' | 'declined' | 'withdrawn' | 'left';

export type GroupSpace = {
    id: string;
    title: string;
    status: CourseSpaceStatus;
};

export type GroupMember = {
    membership_id: string;
    student_id: string;
    name: string;
    joined_at: string | null;
};

export type GroupInvitation = {
    membership_id: string;
    student_id: string;
    name: string;
};

export type GroupDetails = {
    id: string;
    name: string;
    owner: { id: string; name: string };
    members: GroupMember[];
    invitations: GroupInvitation[];
    own: { membership_id: string; status: GroupMembershipStatus } | null;
    can: {
        invite: boolean;
        leave: boolean;
        update: boolean;
        transferOwnership: boolean;
        delete: boolean;
        respond: boolean;
    };
};

export type InvitableStudent = {
    id: string;
    name: string;
};

export type ActivityKind =
    'quiz' | 'poll' | 'topic_selection' | 'topic_proposal' | 'slot_booking';

export type ActivityStatus = 'draft' | 'published' | 'closed';

export type SubmissionStatus = 'submitted' | 'needs_grading' | 'graded';

export type WizardStepKey = 'basics' | 'settings' | 'content' | 'review';

export type ActivitySummary = {
    id: string;
    title: string;
    type: ActivityKind;
    status: ActivityStatus;
    course_space_id: string;
    course_space_title: string;
    deadline_at: string | null;
    published_at: string | null;
};

export type ActivityDetails = ActivitySummary & {
    description: string | null;
};

export type WizardActivity = {
    id: string;
    type: ActivityKind;
    status: ActivityStatus;
    course_space_id: string;
    course_space_title: string;
    published_at: string | null;
};

export type WizardStep = {
    key: WizardStepKey;
    /** Vue page (under resources/js/pages) of a type-owned step. */
    component: string | null;
    complete: boolean;
};

export type WizardBasics = {
    course_space_id: string | null;
    type: ActivityKind | null;
    title: string;
    description: string | null;
    deadline_at: string | null;
};

export type StepData = Record<string, unknown>;

export type StepErrors = Record<string, string>;

export type CourseSpaceOption = {
    id: string;
    title: string;
};

export type Submission = {
    id: string;
    participant: string;
    submitted_at: string;
    status: SubmissionStatus;
    earned_points: string | null;
    max_points: string | null;
    url: string | null;
};

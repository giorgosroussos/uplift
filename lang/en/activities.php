<?php

/*
| Activities (UC-022, UC-028): the activity list, the creation wizard and
| the submissions overview. Every key here must also exist in
| lang/el/activities.php.
*/

return [

    'nav' => 'Activities',

    'types' => [
        'quiz' => 'Quiz',
        'poll' => 'Poll',
        'topic_selection' => 'Topic selection',
        'topic_proposal' => 'Topic proposal',
        'slot_booking' => 'Slot booking',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'closed' => 'Closed',
    ],

    'index' => [
        'title' => 'Activities',
        'create' => 'New activity',
        'empty_teacher' => 'You have not created any activity yet.',
        'empty_student' => 'There are no activities in your course spaces yet.',
        'empty_org_admin' => 'Activities are managed by the teachers of their course spaces.',
        'course_space' => 'Course space',
        'deadline' => 'Deadline',
        'no_deadline' => 'No deadline',
    ],

    'show' => [
        'course_space' => 'Course space',
        'type' => 'Type',
        'status' => 'Status',
        'deadline' => 'Deadline',
        'no_deadline' => 'No deadline',
        'published_at' => 'Published',
        'edit' => 'Edit',
        'publish' => 'Publish',
        'close' => 'Close activity',
        'submissions' => 'Submissions',
        'draft_notice' => 'This activity is a draft: students do not see it until you publish it.',
        'closed_notice' => 'This activity is closed.',
    ],

    'wizard' => [
        'title_create' => 'New activity',
        'title_edit' => 'Edit activity',
        'steps' => [
            'basics' => 'Basics',
            'settings' => 'Settings',
            'content' => 'Content',
            'review' => 'Review',
        ],
        'step_of' => 'Step :current of :total',
        'completed' => 'Completed',
        'next' => 'Save and continue',
        'back' => 'Back',
        'save_draft' => 'Save as draft',
        'done' => 'Done',
        'publish' => 'Publish',
        'saving' => 'Saving…',
        'saved' => 'All changes saved',
        'save_failed' => 'Your changes could not be saved. Check the highlighted fields and try again.',
        'no_types' => 'No activity types are available yet.',
        'no_course_spaces' => 'You need an active course space of your own before you can create an activity.',
        'choose_course_space' => 'Choose a course space',
        'choose_type' => 'Choose a type',
        'type_fixed' => 'The course space and the type cannot be changed after the first save.',
        'type_unavailable' => 'This activity type is not available yet, so its settings cannot be edited.',
        'grading_locked' => 'This activity already has submissions: settings that change grading cannot be edited.',
        'review_hint' => 'Check the activity. You can publish it now or keep it as a draft and come back later.',
        'review_incomplete' => 'Complete every step before publishing.',
        'published_notice' => 'This activity is published. Changes are saved as you go.',
        'individual' => 'Individual activity',
    ],

    'submissions' => [
        'title' => 'Submissions',
        'filter' => 'Status',
        'all' => 'All',
        'participant' => 'Student',
        'submitted_at' => 'Submitted',
        'status' => 'Status',
        'score' => 'Score',
        'open' => 'Open',
        'empty' => 'No submissions yet.',
        'empty_filtered' => 'No submissions with this status.',
        'statuses' => [
            'submitted' => 'Submitted',
            'needs_grading' => 'Needs grading',
            'graded' => 'Graded',
        ],
    ],

    'attributes' => [
        'course_space_id' => 'course space',
        'type' => 'type',
        'title' => 'title',
        'description' => 'description',
        'deadline_at' => 'deadline',
    ],

    'form' => [
        'course_space' => 'Course space',
        'type' => 'Type',
        'title' => 'Title',
        'description' => 'Description',
        'deadline' => 'Deadline (optional)',
    ],

    'errors' => [
        'deadline_passed' => 'The deadline has already passed. Choose a later deadline or remove it.',
        'grading_locked' => 'This activity already has submissions, so settings that change grading cannot be edited.',
    ],

    'problems' => [
        'validation' => 'The data is not valid.',
        'grading_locked' => 'Grading settings are locked.',
    ],

    'flash' => [
        'published' => 'Activity published.',
        'closed' => 'Activity closed.',
    ],

];

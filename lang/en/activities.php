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
        'open_quiz' => 'Open quiz',
        'open_poll' => 'Open poll',
        'open_topics' => 'Open topics',
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
        'group_hint' => 'Students take part in groups of the course space: one member acts for the group and every member sees the result. This cannot be changed once anyone has taken part.',
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
        'poll_anonymous' => 'A poll is anonymous, so it has no submissions. Its results show the votes per option and the number of participants.',
        'poll_results' => 'View poll results',
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
        'is_group' => 'group activity',
        'group_size' => 'maximum group size',
    ],

    'form' => [
        'course_space' => 'Course space',
        'type' => 'Type',
        'title' => 'Title',
        'description' => 'Description',
        'deadline' => 'Deadline (optional)',
        'group_activity' => 'Group activity',
        'group_size' => 'Maximum group size',
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

    'poll' => [
        'settings' => [
            'max_choices' => 'Options a student may choose',
            'max_choices_hint' => 'Students choose one option, or up to this number of options.',
            'results_visibility' => 'Students see the results',
        ],
        'visibility' => [
            'after_vote' => 'Right after voting',
            'after_close' => 'Only after the poll closes',
        ],
        'options' => [
            'title' => 'Options',
            'hint' => 'Add between :min and :max options. Drag them to change their order.',
            'label' => 'Option :number',
            'add' => 'Add option',
            'remove' => 'Remove option',
            'reorder' => 'Drag to reorder',
            'locked' => 'Students have already voted: the options cannot be changed anymore.',
        ],
        'attributes' => [
            'options' => 'options',
            'option' => 'option',
            'label' => 'option text',
        ],
        'errors' => [
            'settings_first' => 'Save the poll settings first.',
            'settings_missing' => 'The poll settings are missing.',
            'min_options' => 'A poll needs at least :min options.',
            'max_options' => 'A poll can have at most :max options.',
            'duplicate_label' => 'Every option needs a different text.',
            'foreign_id' => 'This option does not belong to this poll.',
            'max_choices_above_options' => 'Students cannot choose more options than the poll has (:count).',
        ],
        'show' => [
            'anonymous' => 'This poll is anonymous: nobody sees who voted or what anyone voted.',
            'choose_one' => 'Choose one option.',
            'choose_up_to' => 'Choose up to :max options.',
            'submit' => 'Submit vote',
            'final' => 'Your vote cannot be changed after you submit it.',
            'voted' => 'You have voted. Thank you!',
            'closed' => 'This poll is closed.',
            'results' => 'Results',
            'results_after_close' => 'The results will be shown when the poll closes.',
            'results_after_vote' => 'The results will be shown after you vote.',
            'participants' => '{0} Nobody has voted yet|{1} 1 participant|[2,*] :count participants',
            'votes' => '{0} 0 votes|{1} 1 vote|[2,*] :count votes',
        ],
        'vote' => [
            'errors' => [
                'choose' => 'Choose at least one option.',
            ],
            'refused' => [
                'closed' => 'This poll is closed, so you can no longer vote.',
                'already_voted' => 'You have already voted in this poll. A vote cannot be changed.',
                'too_many' => 'You can choose at most :max options.',
                'unknown_option' => 'Choose only options of this poll.',
            ],
        ],
        'flash' => [
            'voted' => 'Your vote has been recorded.',
        ],
    ],

    'topics' => [
        'editor' => [
            'title' => 'Topics',
            'hint' => 'Add the topics students choose from and how many students (or groups) each topic takes.',
            'topic_title' => 'Topic :number',
            'description' => 'Description (optional)',
            'capacity' => 'Places',
            'add' => 'Add topic',
            'remove' => 'Remove topic',
            'held_notice' => 'Topics that are already chosen cannot be removed, and their places cannot drop below the current holders.',
        ],
        'attributes' => [
            'topics' => 'topics',
            'topic' => 'topic',
            'title' => 'topic title',
            'description' => 'topic description',
            'capacity' => 'places',
            'group' => 'group',
        ],
        'errors' => [
            'min_topics' => 'A topic selection needs at least :min topic.',
            'max_topics' => 'A topic selection can have at most :max topics.',
            'foreign_id' => 'This topic does not belong to this activity.',
            'capacity_below_holders' => 'This topic is already chosen :count times, so it needs at least :count places.',
            'remove_held' => 'A topic that has already been chosen cannot be removed.',
        ],
        'show' => [
            'remaining' => '{0} Full (:capacity places)|{1} 1 place left of :capacity|[2,*] :count places left of :capacity',
            'your_choice' => 'Your topic',
            'group_choice' => 'Topic of :group',
            'no_choice' => 'You have not chosen a topic yet.',
            'group_no_choice' => ':group has not chosen a topic yet.',
            'choose' => 'Choose this topic',
            'switch' => 'Switch to this topic',
            'chosen' => 'Chosen',
            'cancel' => 'Cancel my topic',
            'cancel_group' => 'Cancel the group\'s topic',
            'acting_group' => 'Choose for the group',
            'group_option' => ':name (:members members)',
            'no_groups' => 'This is a group activity: create or join a group in the course space to choose a topic.',
            'group_activity' => 'Group activity: one member chooses for the group, for groups of up to :size members.',
            'group_too_large' => 'This group has more members than the activity allows.',
            'group_in_other' => 'A member of this group already takes part through another group.',
            'closed' => 'The topic selection is closed: topics can no longer be chosen, changed or cancelled.',
            'deadline_hint' => 'You can change or cancel your topic until the deadline.',
            'holders' => 'Chosen by',
            'no_holders' => 'Nobody yet',
        ],
        'choose' => [
            'errors' => [
                'choose' => 'Choose a topic.',
            ],
            'refused' => [
                'closed' => 'This activity is closed, so topics can no longer be chosen, changed or cancelled.',
                'deadline_passed' => 'The deadline has passed, so topics can no longer be chosen, changed or cancelled.',
                'unknown_topic' => 'Choose a topic of this activity.',
                'full' => 'This topic is full. Choose another topic.',
                'choose_group' => 'Choose the group you are choosing for.',
                'not_your_group' => 'You can only act for a group of this course space you are a member of.',
                'group_too_large' => 'This group has more members than the activity allows (:size).',
                'member_in_other_group' => 'A member of this group already takes part in this activity through another group.',
                'no_choice' => 'There is no topic to cancel.',
                'not_yours' => 'You can only change your own or your group\'s topic.',
            ],
        ],
        'flash' => [
            'chosen' => 'Your topic has been saved.',
            'cancelled' => 'Your topic has been cancelled.',
        ],
    ],

];

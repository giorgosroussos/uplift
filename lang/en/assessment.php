<?php

/*
| Questions and the personal question bank (UC-023): UI strings of the Vue
| pages and server-side messages. Every key here must also exist in
| lang/el/assessment.php.
*/

return [

    'nav' => [
        'results' => 'My results',
        'question_bank' => 'Question bank',
    ],

    'index' => [
        'title' => 'Question bank',
        'heading' => 'My question bank',
        'create' => 'New question',
        'import' => 'Import from CSV',
        'empty' => 'Your question bank is empty. Create a question to reuse it in your quizzes.',
        'points' => 'Points',
        'items' => 'Options or pairs',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'confirm_delete' => 'Delete this question from your bank?',
        'confirm_delete_body' => 'Quizzes that already contain a copy of it are not affected.',
        'cancel' => 'Cancel',
        'previous' => 'Previous',
        'next' => 'Next',
    ],

    'create' => [
        'title' => 'New question',
        'submit' => 'Save question',
    ],

    'edit' => [
        'title' => 'Edit question',
        'submit' => 'Save changes',
    ],

    'form' => [
        'type' => 'Question type',
        'prompt' => 'Question',
        'points' => 'Points',
        'points_hint' => 'More than 0 and at most 100, with up to 2 decimal places.',
        'options' => 'Options',
        'options_hint' => 'From 2 to 10 options. Drag them to change their order.',
        'options_hint_single' => 'Mark exactly one correct option.',
        'options_hint_multiple' => 'Mark at least one correct option.',
        'options_hint_true_false' => 'Mark the correct answer.',
        'pairs' => 'Matching pairs',
        'pairs_hint' => 'From 2 to 10 correct pairs. Drag them to change their order.',
        'free_text_hint' => 'Students write their answer; you grade it.',
        'option_text' => 'Option :number',
        'left_text' => 'Left item :number',
        'right_text' => 'Right item :number',
        'correct' => 'Correct',
        'add_option' => 'Add option',
        'add_pair' => 'Add pair',
        'remove' => 'Remove',
        'drag' => 'Drag to reorder',
        'cancel' => 'Cancel',
    ],

    'types' => [
        'single_choice' => 'Single choice',
        'multiple_choice' => 'Multiple choice',
        'true_false' => 'True / false',
        'matching' => 'Matching',
        'free_text' => 'Free text',
    ],

    // DATA-016: true/false options are stored as 'true' and 'false' and
    // shown only through these keys.
    'true_false' => [
        'true' => 'True',
        'false' => 'False',
    ],

    'flash' => [
        'created' => 'The question was added to your bank.',
        'updated' => 'The question was saved.',
        'deleted' => 'The question was deleted from your bank.',
    ],

    // UC-030 and DATA-017: bulk import of questions from a CSV file. The
    // column names of the file are fixed English names in both languages.
    'import' => [
        'title' => 'Import questions',
        'heading' => 'Import questions from CSV',
        'intro' => 'Upload a CSV file to add many questions to your bank at once. You will see a report of every row before anything is imported.',
        'template' => 'Download the template',
        'template_hint' => 'The template has the required columns and one example row of each question type.',
        'format_heading' => 'File format',
        'format' => [
            'encoding' => 'Save the file as "CSV UTF-8". Columns may be separated by commas or semicolons; points may use a dot or a comma for decimals.',
            'columns' => 'The first row holds the column names type, prompt, points, correct and option_1 to option_10, in any order.',
            'type' => 'type is one of single_choice, multiple_choice, true_false, matching, free_text.',
            'choice' => 'Single and multiple choice: write the options in option_1, option_2 and so on, and the numbers of the correct options in correct, separated by | (for example 1|3).',
            'true_false' => 'True / false: leave the options empty and write true or false in correct.',
            'matching' => 'Matching: write each pair as "left => right" in option_1, option_2 and so on, and leave correct empty.',
            'free_text' => 'Free text: leave the options and correct empty.',
            'limits' => 'At most 500 questions and 1 MB per file.',
        ],
        'file' => 'CSV file',
        'upload' => 'Upload and check',
        'cancel' => 'Cancel',
        'report' => [
            'title' => 'Import report',
            'summary' => ':valid valid rows, :invalid rows with errors.',
            'skipped_notice' => 'Rows with errors are skipped; only the valid rows are imported.',
            'none_valid' => 'No row is valid, so there is nothing to import. Correct the file and upload it again.',
            'refused' => 'This file cannot be imported.',
            'line' => 'Line',
            'question' => 'Question',
            'type' => 'Type',
            'result' => 'Result',
            'valid' => 'Valid',
            'unknown_type' => 'Unknown type',
            'confirm' => 'Import :count valid questions',
            'cancel' => 'Cancel import',
        ],
        'refused' => [
            'not_utf8' => 'The file is not UTF-8 encoded. In Excel, choose "Save as" with the type "CSV UTF-8 (Comma delimited)" and upload it again.',
            'too_large' => 'The file may be at most 1 MB.',
            'empty' => 'The file is empty.',
            'unreadable' => 'The file could not be read.',
            'missing_headers' => 'The first row is missing these columns: :columns.',
            'unknown_headers' => 'The first row has unknown or repeated columns: :columns.',
            'too_many_rows' => 'The file may have at most :max questions.',
        ],
        'errors' => [
            'file_required' => 'Choose a CSV file.',
            'file_type' => 'The file must be a .csv file.',
            'correct_numbers' => 'The correct column must list numbers of the given options, separated by :separator.',
            'true_false_options' => 'A true/false row has no options; write true or false in the correct column.',
            'true_false_correct' => 'The correct column of a true/false row must be true or false.',
            'correct_not_allowed' => 'The correct column must be empty for this question type.',
            'pair_format' => 'Option :number must be a pair written as "left :separator right".',
            'extra_cells' => 'This row has more cells than the first row has columns.',
            'no_valid_rows' => 'No row of this file is valid, so nothing can be imported.',
        ],
        'flash' => [
            'imported' => 'Imported :imported questions; skipped :skipped rows with errors.',
            'cancelled' => 'The import was cancelled and the file deleted.',
        ],
        // Example rows of the downloadable template.
        'examples' => [
            'single_prompt' => 'What is the capital of Greece?',
            'single_option_1' => 'Thessaloniki',
            'single_option_2' => 'Athens',
            'single_option_3' => 'Patras',
            'multiple_prompt' => 'Which of these are prime numbers?',
            'multiple_option_1' => '2',
            'multiple_option_2' => '4',
            'multiple_option_3' => '7',
            'true_false_prompt' => 'Water boils at 100 °C at sea level.',
            'matching_prompt' => 'Match each country with its capital.',
            'matching_left_1' => 'Greece',
            'matching_right_1' => 'Athens',
            'matching_left_2' => 'France',
            'matching_right_2' => 'Paris',
            'matching_left_3' => 'Italy',
            'matching_right_3' => 'Rome',
            'free_text_prompt' => 'Explain in a few sentences why the sky is blue.',
        ],
    ],

    'validation' => [
        'points_decimals' => 'Points may have at most 2 decimal places.',
        'points_range' => 'Points must be more than 0 and at most 100.',
        'options_count' => 'A choice question needs from 2 to 10 options.',
        'true_false_options' => 'A true/false question has exactly the two options true and false.',
        'no_options' => 'This question type has no options.',
        'pairs_count' => 'A matching question needs from 2 to 10 pairs.',
        'no_pairs' => 'This question type has no matching pairs.',
        'exactly_one_correct' => 'Mark exactly one option as correct.',
        'at_least_one_correct' => 'Mark at least one option as correct.',
    ],

    'attributes' => [
        'type' => 'question type',
        'prompt' => 'question',
        'points' => 'points',
        'options' => 'options',
        'option_text' => 'option text',
        'pairs' => 'matching pairs',
        'left_text' => 'left item',
        'right_text' => 'right item',
    ],

    // UC-022: the quiz steps of the activity wizard.
    'quiz' => [
        'modes' => [
            'self_paced' => 'Self-paced',
            'live' => 'Live',
        ],
        'visibility' => [
            'immediate' => 'Right after submitting',
            'after_deadline' => 'After the deadline',
            'hidden' => 'Never',
        ],
        'settings' => [
            'mode' => 'Mode',
            'time_limit' => 'Time limit (minutes)',
            'time_limit_hint' => 'Leave empty for no time limit.',
            'max_attempts' => 'Attempts per student',
            'negative_marking' => 'Negative marking for wrong answers',
            'negative_mark_fraction' => 'Deduction (% of the question\'s points)',
            'negative_mark_hint' => 'More than 0 and at most 100.',
            'pass_mark' => 'Pass mark (%)',
            'pass_mark_hint' => 'Optional; from 0 to 100.',
            'score_visibility' => 'Students see their score',
            'answers_visibility' => 'Students see the correct answers',
        ],
        'questions' => [
            'hint' => 'Write new questions or copy them from your question bank, and drag them to change their order.',
            'empty' => 'This quiz has no questions yet.',
            'untitled' => 'New question',
            'points' => ':points points',
            'expand' => 'Edit question',
            'collapse' => 'Close question',
            'remove' => 'Remove question',
            'has_errors' => 'This question has errors.',
            'add' => 'New question',
            'from_bank' => 'Add from question bank',
        ],
        'bank' => [
            'title' => 'Your question bank',
            'hint' => 'A copy is added to the quiz; later changes to the bank question do not affect it.',
            'failed' => 'Your question bank could not be loaded.',
            'empty' => 'Your question bank is empty.',
            'added' => 'Added',
            'copy' => 'Add',
        ],
        'errors' => [
            'settings_first' => 'Save the quiz settings first.',
            'settings_missing' => 'The quiz settings are missing.',
            'no_questions' => 'A quiz needs at least one question.',
            'live_free_text' => 'A live quiz cannot have free-text questions.',
            'duplicate_question' => 'This question appears twice.',
            'foreign_id' => 'This item does not belong to this quiz.',
        ],
    ],

    // UC-012: taking a self-paced quiz.
    'attempt' => [
        'statuses' => [
            'in_progress' => 'In progress',
            'submitted' => 'Submitted',
            'graded' => 'Graded',
        ],
        'score' => ':earned / :max points',
        'your_score' => 'Your score: :earned / :max points',
        'provisional' => '(provisional)',
        'quiz' => [
            'time_limit' => 'Time limit',
            'minutes' => ':minutes minutes',
            'no_time_limit' => 'No time limit',
            'questions' => 'Questions',
            'attempts' => 'Attempts',
            'attempts_left' => ':left of :max left',
            'negative_marking' => 'Wrong answers lose :fraction% of the question\'s points. Unanswered questions lose nothing.',
            'start' => 'Start attempt',
            'continue' => 'Continue attempt',
            'closed' => 'This quiz is closed.',
            'deadline_passed' => 'The deadline of this quiz has passed.',
            'no_attempts_left' => 'You have no attempts left.',
            'back' => 'Back to the activity',
            'your_attempts' => 'Your attempts',
            'attempt_number' => 'Attempt :number',
            // SCOPE-012, SCOPE-025: group quizzes.
            'group_quiz' => 'Group quiz: one member answers and submits for the whole group, and every member gets the same grade.',
            'group_size' => 'Group size: at most :size members',
            'group' => 'Group',
            'choose_group' => 'Take part with group',
            'group_option' => ':name (:members members, :left attempts left)',
            'no_groups' => 'You are not a member of a group in this course space. Create or join a group to take this quiz.',
            'open_groups' => 'Go to the groups',
            'no_group_can_start' => 'None of your groups can start an attempt now.',
            'group_attempt' => 'Group :group',
        ],
        'take' => [
            'question_number' => 'Question :number',
            'time_left' => 'Time left: :time',
            'no_time_limit' => 'No time limit',
            'multiple_hint' => 'Select all correct answers.',
            'choose_match' => 'Choose…',
            'your_answer' => 'Your answer',
            'save_saving' => 'Saving…',
            'save_saved' => 'Saved',
            'save_failed' => 'Not saved',
            'submit' => 'Submit attempt',
            'confirm_submit' => 'Submit this attempt?',
            'confirm_submit_body' => 'You answered :answered of :total questions. You cannot change your answers after submitting.',
            'cancel' => 'Cancel',
            'back' => 'Back to the quiz',
            'provisional_notice' => 'Your score is provisional until your teacher grades the free-text answers.',
            'score_hidden' => 'Your attempt was submitted. Your teacher has not made the score visible yet.',
            'group_notice' => 'Group attempt of :group: any member can answer and submit it for the whole group.',
        ],
        'refused' => [
            'not_self_paced' => 'This quiz is not taken in attempts.',
            'not_open' => 'This quiz is not open.',
            'deadline_passed' => 'The deadline of this quiz has passed.',
            'in_progress' => 'You already have an attempt in progress. Continue that attempt.',
            'no_attempts_left' => 'You have no attempts left.',
            'choose_group' => 'Choose one of your groups in this course space.',
            'group_too_large' => 'This group has more members than the quiz allows.',
            'group_in_progress' => 'This group already has an attempt in progress. Continue that attempt.',
            'group_no_attempts_left' => 'This group has no attempts left.',
            'other_group' => 'A member of this group already takes part in this quiz through another group.',
        ],
        'errors' => [
            'one_option' => 'Select only one option.',
            'foreign_item' => 'This item does not belong to this question.',
            'duplicate_item' => 'This item appears twice.',
            'closed' => 'Your attempt has ended; answers given after the end are not counted.',
        ],
        'problems' => [
            'closed' => 'Attempt not in progress',
        ],
        'flash' => [
            'submitted' => 'Your attempt was submitted.',
        ],
    ],

    // UC-017, UC-026: grades on the organisation's grading scale (DATA-005).
    'grade' => [
        'scale_0_10' => ':value / 10',
        'scale_0_100' => ':value / 100',
        'points_pass_mark' => ':earned / :max points',
        'percentage' => ':percentage%',
        'passed' => 'Passed',
        'failed' => 'Not passed',
        'provisional' => 'Provisional',
        'final' => 'Final',
        'hidden' => 'Not visible yet',
    ],

    // UC-017: a student's own results.
    'results' => [
        'index' => [
            'title' => 'My results',
            'empty' => 'You have no results yet. They appear here once you submit a quiz.',
            'attempt' => 'Attempt :number',
            'submitted_at' => 'Submitted',
            'grade' => 'Grade',
            'status' => 'Status',
            'has_feedback' => 'Teacher feedback',
            'open' => 'View result',
        ],
        'show' => [
            'title' => 'Result',
            'back' => 'Back to my results',
            'provisional_notice' => 'This result is provisional until your teacher grades the free-text answers.',
            'score_hidden' => 'Your teacher has not made the score visible yet.',
            'answers_hidden' => 'The correct answers are not visible yet.',
            'group' => 'Group attempt of :group: every member gets this grade.',
        ],
    ],

    // UC-017, UC-026: a submitted question with its answer.
    'review' => [
        'question_number' => 'Question :number',
        'your_answer' => 'Answer',
        'no_answer' => 'Not answered',
        'selected' => 'Selected',
        'correct' => 'Correct',
        'correct_answer' => 'Correct answer: :answer',
        'matched_to' => ':left: :right',
        'points_awarded' => ':awarded / :points points',
        'feedback' => 'Teacher feedback',
        'grading_statuses' => [
            'pending' => 'Awaiting grading',
            'auto_graded' => 'Graded automatically',
            'graded' => 'Graded by the teacher',
        ],
    ],

    // UC-026: the owning teacher grades an attempt's answers.
    'grading' => [
        'title' => 'Grade attempt',
        'student' => 'Student',
        'group' => 'Group',
        'back' => 'Back to submissions',
        'points' => 'Points',
        'points_hint' => 'From 0 to :max, with up to 2 decimal places.',
        'feedback' => 'Feedback (optional)',
        'save' => 'Save grade',
        'in_progress_notice' => 'This attempt is still in progress. You can grade it once it is submitted.',
        'provisional_notice' => 'The score is provisional until every free-text answer is graded.',
        'final_notice' => 'All answers are graded; the score is final.',
        'errors' => [
            'points_format' => 'Enter the points as a number with up to 2 decimal places.',
            'points_max' => 'The points must be from 0 to :max.',
            'not_submitted' => 'The attempt has not been submitted yet.',
            'feedback_invalid' => 'The feedback must be text.',
            'feedback_length' => 'The feedback may have at most :max characters.',
        ],
        'flash' => [
            'graded' => 'The grade was saved.',
        ],
    ],

];

<?php

/*
| Questions and the personal question bank (UC-023): UI strings of the Vue
| pages and server-side messages. Every key here must also exist in
| lang/el/assessment.php.
*/

return [

    'nav' => [
        'question_bank' => 'Question bank',
    ],

    'index' => [
        'title' => 'Question bank',
        'heading' => 'My question bank',
        'create' => 'New question',
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

];

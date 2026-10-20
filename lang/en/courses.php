<?php

/*
| Course spaces and enrolment (UC-011, UC-021): UI strings of the Vue pages
| and server-side messages. Every key here must also exist in
| lang/el/courses.php.
*/

return [

    'nav' => [
        'courses' => 'Course spaces',
        'catalogue' => 'Catalogue',
    ],

    'index' => [
        'title' => 'Course spaces',
        'heading_student' => 'My course spaces',
        'heading_teacher' => 'My course spaces',
        'heading_org_admin' => 'Course spaces of the organisation',
        'create' => 'New course space',
        'browse' => 'Browse the catalogue',
        'empty_student' => 'You are not enrolled in any course space yet.',
        'empty_teacher' => 'You have not created any course space yet.',
        'empty_org_admin' => 'There are no course spaces in your organisation yet.',
    ],

    'catalogue' => [
        'title' => 'Catalogue',
        'heading' => 'Catalogue of course spaces',
        'search' => 'Search',
        'search_placeholder' => 'Title or description',
        'teacher' => 'Teacher',
        'all_teachers' => 'All teachers',
        'apply' => 'Search',
        'empty' => 'No active course spaces match your search.',
        'previous' => 'Previous',
        'next' => 'Next',
    ],

    'create' => [
        'title' => 'New course space',
        'submit' => 'Create course space',
    ],

    'edit' => [
        'title' => 'Edit course space',
        'submit' => 'Save changes',
    ],

    'form' => [
        'title' => 'Title',
        'description' => 'Description',
        'enrolment_policy' => 'Enrolment',
        'policy_open' => 'Open: students enrol freely',
        'policy_key' => 'Enrolment key: students enter the key you share',
        'enrolment_key' => 'Enrolment key',
        'cancel' => 'Cancel',
    ],

    'show' => [
        'teacher' => 'Teacher',
        'status_active' => 'Active',
        'status_archived' => 'Archived',
        'policy_open' => 'Open enrolment',
        'policy_key' => 'Enrolment with key',
        'enrolment_key' => 'Enrolment key',
        'edit' => 'Edit',
        'archive' => 'Archive',
        'restore' => 'Make active again',
        'enrolled' => 'You are enrolled in this course space.',
        'enrol' => 'Enrol',
        'enrol_key_hint' => 'Enter the enrolment key you got from your teacher.',
        'archived_notice' => 'This course space is archived and hidden from students. If you make it active again, it has no students: they enrol again.',
        'confirm_archive_title' => 'Archive this course space?',
        'confirm_archive_text' => 'All students are unenrolled (:count enrolled now) and no longer see the course space. This cannot be undone: if you make it active again, the students have to enrol again.',
        'confirm_archive' => 'Archive and unenrol all students',
        'cancel' => 'Cancel',
        'enrolments_count' => 'Enrolled students',
        'students' => 'Enrolled students',
        'no_students' => 'No students are enrolled yet.',
        'student_name' => 'Name',
        'student_email' => 'Email',
        'enrolled_at' => 'Enrolled on',
        'remove' => 'Remove',
        'add_student' => 'Add a student',
        'choose_student' => 'Choose a student',
        'add' => 'Add',
        'no_available_students' => 'Every student of the organisation is already enrolled.',
    ],

    'announcements' => [
        'heading' => 'Announcements',
        'empty' => 'There are no announcements yet.',
        'new' => 'New announcement',
        'title' => 'Title',
        'body' => 'Text',
        'post' => 'Publish',
        'published_by' => 'By :author, :date',
        'edit' => 'Edit',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'confirm_delete' => 'Delete this announcement?',
    ],

    'attributes' => [
        'title' => 'title',
        'description' => 'description',
        'enrolment_policy' => 'enrolment',
        'enrolment_key' => 'enrolment key',
        'student' => 'student',
        'announcement_title' => 'title',
        'announcement_body' => 'text',
    ],

    'errors' => [
        'wrong_key' => 'The enrolment key is not correct.',
        'archived' => 'This course space is archived and accepts no new enrolments.',
        'not_a_student' => 'Choose an active student of your organisation.',
        'invalid_transition' => 'This course space cannot change to that status.',
    ],

    'flash' => [
        'created' => 'Course space created.',
        'updated' => 'Course space saved.',
        'archived' => 'Course space archived.',
        'restored' => 'Course space is active again.',
        'enrolled' => 'You are enrolled.',
        'student_added' => 'Student added.',
        'student_removed' => 'Student removed.',
        'announcement_posted' => 'Announcement published.',
        'announcement_updated' => 'Announcement saved.',
        'announcement_deleted' => 'Announcement deleted.',
    ],

];

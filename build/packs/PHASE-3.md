<!-- GENERATED — do not edit -->
# Pack PHASE-3 — Activities framework and self-paced quizzes end to end: wizard, question bank, taking, grading, results

_design v3.5.0 · 174b6708ecea_

Depends on: PHASE-2 · environment: development · components: CMP-Activities, CMP-Assessment, CMP-Notifications

## Tasks

| task | title | depends on | writes |
|---|---|---|---|
| P3-T1 | Activity base, ActivityType contract and wizard shell | — | CMP-Activities |
| P3-T2 | Personal question bank | — | CMP-Assessment |
| P3-T3 | Quiz activity type | P3-T1, P3-T2 | CMP-Assessment |
| P3-T4 | Take a self-paced quiz | P3-T3 | CMP-Assessment, CMP-Activities |
| P3-T5 | Grading, results and quiz submissions | P3-T4 | CMP-Assessment |
| P3-T6 | CSV question import | P3-T2 | CMP-Assessment |
| P3-T7 | Notifications for new activities and grades | P3-T1, P3-T5 | CMP-Notifications, CMP-Assessment |

Execution waves: [P3-T1, P3-T2] → [P3-T3] → [P3-T4] → [P3-T5] → [P3-T6] → [P3-T7]

## Out of scope (do not build)

- Polls, topics, slots, groups and group attempts (PHASE-4)
- Live quizzes; the wizard offers only self-paced mode (PHASE-5)
- XP, badges, leaderboards (PHASE-6)
- Analytics and dashboards (PHASE-7)

## Use cases

#### UC-022 — Create activity (must)

Actors: ACT-Teacher · Goal: Create an activity of one of the five types through a multi-step wizard with autosave, as draft or published

Main flow:
1. Teacher enters basics: title, description, course space, deadline, individual or group with group size
2. Chooses the type (quiz, poll, topic selection, topic proposal, slot booking) and its settings
3. For quizzes: adds questions of the five types (new or from the question bank), orders them by drag and drop, sets time limit, attempts, negative marking, pass mark and result visibility
4. Reviews and saves as draft or publishes; progress is autosaved between steps

Exceptions:
- Invalid step: cannot publish until every required field is valid
- Quiz with any attempt, including one in progress: settings that change grading (questions, options, pairs, points, correct answers, negative marking) cannot be edited (SCOPE-023)

#### UC-023 — Manage question bank (should)

Actors: ACT-Teacher · Goal: Create and edit reusable questions of the five question types

Main flow:
1. Teacher opens their question bank (newest first)
2. Creates a question: chooses the type, writes the prompt, sets the points and adds options or matching pairs, ordering them by drag and drop
3. Saves; the question is validated per DATA-016 and kept in the bank
4. Later edits or deletes it; quiz copies are unaffected

Exceptions:
- Question breaks a DATA-016 rule: nothing is saved and the field shows the reason
- Another teacher, an org admin or a student opens a bank question or the bank pages: refused (OWN-016)

#### UC-012 — Take self-paced quiz (must)

Actors: ACT-Student · Goal: Answer a timed quiz with single-choice, multiple-choice, true/false, matching and free-text questions, then submit

Main flow:
1. Student starts an attempt of a published quiz before its deadline; the timer starts
2. Answers questions of the five types; answers are saved as they go
3. Submits, or the attempt is auto-submitted when the time runs out
4. Auto-gradable questions are scored at once; free-text answers wait for teacher grading
5. Student sees their score immediately unless the teacher configured otherwise

Exceptions:
- No attempts left: a new attempt is refused
- An attempt already in progress: starting another is refused; the student continues the open one
- Teacher closes the activity: every in-progress attempt is submitted with the answers saved so far
- Time runs out: the answers given so far are submitted
- Connection lost: saved answers are kept; the timer keeps running on the server
- Quiz with free-text questions: the score is shown as provisional until graded

#### UC-026 — Grade free-text answers (must)

Actors: ACT-Teacher · Goal: Grade deferred free-text answers with a score and feedback

Main flow:
1. Teacher opens the answers waiting for grading, per activity
2. Enters points (0 to the question's maximum) and optional feedback
3. When all free-text answers of an attempt are graded, its score becomes final; the student is notified when the final score becomes visible to them (DEC-003)

#### UC-017 — View my results (must)

Actors: ACT-Student · Goal: See grades and teacher feedback per activity and overall

Main flow:
1. Student opens My results
2. Sees each activity's score on the organisation's grading scale, status (provisional or final) and teacher feedback
3. Opens an activity result; correct answers are shown only if the teacher allowed it

#### UC-028 — Review submissions (must)

Actors: ACT-Teacher · Goal: List submissions per activity with status and jump to grading

Main flow:
1. Teacher opens an activity's submissions: student or group, time, status, score
2. Filters by status (submitted, needs grading, graded) and opens one to grade

#### UC-030 — Bulk import from CSV (should)

Actors: ACT-Teacher · Goal: Upload a CSV of questions into the question bank, review a per-row validation report, confirm the import

Main flow:
1. Teacher opens the import page of their question bank and may download the template CSV
2. Uploads a CSV file in the DATA-017 format
3. Sees the report: every row with its line number and 'valid' or its errors
4. Confirms; the valid rows become bank questions in file order and the invalid ones are skipped
5. Sees the imported and skipped counts; the uploaded file is deleted

Exceptions:
- File not UTF-8, wrong headers, over 500 data rows or over 1 MB: the whole file is refused with the reason
- No valid row: confirm is disabled
- Teacher cancels: nothing is imported and the file is deleted
- Another user confirms or cancels the import: refused

## Entities

#### ENT-Activity — Base table of the five activity types; one subtype row per activity

Owned by CMP-Activities · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| course_space_id | uuid | uuid | → ENT-CourseSpace, required |
| type | enum | string | required |
| title | string | string(255) | required |
| description | text | text |  |
| deadline_at | timestamp | timestamptz |  |
| is_group | boolean | boolean | required |
| group_size | integer | integer |  |
| status | enum | string | required |
| published_at | timestamp | timestamptz |  |

Relations: one-to-one → ENT-Quiz (on delete cascade); one-to-one → ENT-Poll (on delete cascade); one-to-one → ENT-TopicActivity (on delete cascade); one-to-one → ENT-SlotActivity (on delete cascade)

States: initial `draft`; allowed transitions: `draft -> published`, `published -> closed`

#### ENT-Quiz — Quiz subtype of Activity

Owned by CMP-Assessment · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| activity_id | uuid | uuid | → ENT-Activity, required |
| mode | enum | string | required |
| time_limit | duration | integer (seconds) |  |
| live_start_mode | enum | string |  |
| live_scheduled_start_at | timestamp | timestamptz |  |
| max_attempts | integer | integer | required |
| negative_marking | boolean | boolean | required |
| negative_mark_fraction | percentage | decimal(7,4) |  |
| pass_mark_percentage | percentage | decimal(7,4) |  |
| score_visibility | enum | string | required |
| answers_visibility | enum | string | required |

Relations: one-to-many → ENT-QuizQuestion (on delete cascade); one-to-many → ENT-QuizAttempt (on delete restrict); one-to-one → ENT-LiveSession (on delete restrict)

#### ENT-Question — A question of one of five types; the bank is personal to each teacher; adding a bank question to a quiz copies it (DATA-008)

Owned by CMP-Assessment · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| owner_id | uuid | uuid | → ENT-User, required |
| type | enum | string | required |
| prompt | text | text | required |
| points | decimal | decimal | required |
| in_bank | boolean | boolean | required |

Relations: one-to-many → ENT-ChoiceOption (on delete cascade); one-to-many → ENT-MatchingPair (on delete cascade)

#### ENT-ChoiceOption — Option of a single-choice, multiple-choice or true/false question

Owned by CMP-Assessment · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| question_id | uuid | uuid | → ENT-Question, required |
| text | text | text | required |
| is_correct | boolean | boolean | required |
| position | integer | integer | required |

#### ENT-MatchingPair — A correct left-right pair of a matching question

Owned by CMP-Assessment · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| question_id | uuid | uuid | → ENT-Question, required |
| left_text | text | text | required |
| right_text | text | text | required |
| position | integer | integer | required |

#### ENT-QuizQuestion — Ordered placement of a (copied) question in a quiz

Owned by CMP-Assessment · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| quiz_id | uuid | uuid | → ENT-Quiz, required |
| question_id | uuid | uuid | → ENT-Question, required |
| position | integer | integer | required |

#### ENT-QuizAttempt — One attempt of a self-paced quiz by a student or a group

Owned by CMP-Assessment · retention: Kept while the course space exists; follows the student's erasure (SEC-003) · audit: yes

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| quiz_id | uuid | uuid | → ENT-Quiz, required |
| student_id | uuid | uuid | → ENT-User |
| group_id | uuid | uuid | → ENT-Group |
| submitted_by | uuid | uuid | → ENT-User |
| started_at | timestamp | timestamptz | required |
| ends_at | timestamp | timestamptz |  |
| submitted_at | timestamp | timestamptz |  |
| earned_points | decimal | decimal | personal |
| max_points | decimal | decimal |  |
| status | enum | string | required |
| result_released_at | timestamp | timestamptz |  |

Relations: one-to-many → ENT-Answer (on delete cascade)

States: initial `in_progress`; allowed transitions: `in_progress -> submitted`, `submitted -> graded`

#### ENT-Answer — Answer to one quiz question; structured per type (selected options, matches, or text)

Owned by CMP-Assessment · retention: As ENT-QuizAttempt · audit: yes

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| attempt_id | uuid | uuid | → ENT-QuizAttempt, required |
| quiz_question_id | uuid | uuid | → ENT-QuizQuestion, required |
| text_response | text | text | personal |
| points_awarded | decimal | decimal |  |
| grading_status | enum | string | required |
| feedback | text | text | personal |
| graded_by | uuid | uuid | → ENT-User |
| graded_at | timestamp | timestamptz |  |

Relations: one-to-many → ENT-AnswerSelection (on delete cascade); one-to-many → ENT-AnswerMatch (on delete cascade)

States: initial `pending`; allowed transitions: `pending -> auto_graded`, `pending -> graded`, `auto_graded -> graded`

#### ENT-AnswerSelection — A choice option selected in an answer

Owned by CMP-Assessment · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| answer_id | uuid | uuid | → ENT-Answer, required |
| choice_option_id | uuid | uuid | → ENT-ChoiceOption, required |

#### ENT-AnswerMatch — A left item matched to a right item in an answer

Owned by CMP-Assessment · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| answer_id | uuid | uuid | → ENT-Answer, required |
| left_pair_id | uuid | uuid | → ENT-MatchingPair, required |
| right_pair_id | uuid | uuid | → ENT-MatchingPair, required |

## Interfaces

#### Laravel Mail / Notification mail channel (INT-001: CMP-Notifications ↔ EXT-002)

Mechanism: Laravel Notifications over the mail channel (Laravel Mail, SMTP or a Laravel mail driver), always queued; Identity's account emails (Fortify reset, verification, invitation) use the same queued mail channel

Operations:
- `InvitationNotification(invitation_id)`
- `ResetPasswordNotification(user_id, token)`
- `VerifyEmailNotification(user_id)`
- `AnnouncementPublished(announcement_id)`
- `ActivityPublished(activity_id)`
- `GradeFinalised(attempt_id)`
- `GradeChanged(attempt_id)`
- `TopicProposalDecided(proposal_id)`
- `GroupInvitation(group_membership_id), on GroupInvitationCreated (SCOPE-025)`

Failure policy: timeout 10s, retries 5, exponential backoff via queue (1m, 5m, 15m, 1h, 6h), idempotency key —; when unavailable: Emails stay queued and are retried; no user flow is blocked; the in-app notification is created regardless; after the last retry an invitation shows 'email not delivered' to the admin, who can resend it

## External systems in `development`

| id | name | mode | tool | config vars |
|---|---|---|---|---|
| EXT-002 | Email delivery | catcher | Mailpit | MAIL_* |

## Constraints

- MUST store scores as earned points and max points using exact decimal types  (DATA-003)
- MUST convert to the organisation's grading scale only at display or export time  (DATA-003)
- MUST enforce quiz time limits and deadlines on the server  (DATA-004)
- MUST NOT trust client timestamps for timing decisions  (DATA-004)
- MUST round converted grades and percentages half-up to 1 decimal, only at display or export  (DATA-005)
- MUST copy a bank question with its options and pairs when it is added to a quiz  (DATA-008)
- MUST NOT show a teacher's bank to other teachers  (DATA-008)
- MUST refuse a single_choice or true_false question without exactly 1 correct option  (DATA-016)
- MUST refuse a multiple_choice question without at least 1 correct option  (DATA-016)
- MUST refuse choice questions with fewer than 2 or more than 10 options and matching questions with fewer than 2 or more than 10 pairs or an empty side  (DATA-016)
- MUST store true_false option texts as 'true' and 'false' and display them only through translation keys  (DATA-016)
- MUST refuse points outside (0, 100] or with more than 2 decimal places  (DATA-016)
- MUST NOT change or delete quiz copies when a bank question is edited or deleted  (DATA-016)
- MUST accept a time limit only of 1-1440 minutes or none, and max_attempts only of 1-100  (SCOPE-023)
- MUST refuse changes to questions, options, pairs, points, correct answers or negative marking of a quiz once any attempt of it exists, including an in-progress one  (SCOPE-023)
- MUST create answers as pending and move them on submission to auto_graded (choice, true/false, matching) or leave them pending (free text)  (SCOPE-023)
- MUST treat after_deadline score and answer visibility on a quiz without a deadline as visible immediately after submission  (SCOPE-023)
- MUST NOT allow self-paced attempts on a live quiz  (SCOPE-016)
- MUST NOT allow live sessions on a self-paced quiz  (SCOPE-016)
- MUST score multiple choice as points x max(0, correct selected - wrong selected) / correct options, matching as points x correct matches / pairs, single choice and true/false as all or nothing  (SCOPE-024)
- MUST apply negative marking only to answered auto-graded questions that earn 0, never to unanswered or free-text questions  (SCOPE-024)
- MUST NOT let an attempt's earned_points go below 0  (SCOPE-024)
- MUST round each answer's points_awarded half-up to 2 decimals when stored and compute earned_points as their sum  (SCOPE-024)
- MUST set ends_at to the earlier of started_at + time_limit and the activity's deadline, or null when neither exists  (SCOPE-024)
- MUST auto-submit an in-progress attempt at ends_at and when its activity is closed, with the answers saved so far  (SCOPE-024)
- MUST move a submitted attempt to graded once it has no pending answers  (SCOPE-024)
- MUST count in-progress attempts toward max_attempts and refuse a new attempt while one is in progress  (SCOPE-024)
- MUST reject ENT-Activity state transitions that are not listed in its state model
- MUST reject ENT-QuizAttempt state transitions that are not listed in its state model
- MUST NOT write ENT-QuizAttempt fields earned_points to logs or error messages
- MUST reject ENT-Answer state transitions that are not listed in its state model
- MUST NOT write ENT-Answer fields text_response, feedback to logs or error messages
- Percentages MUST use exact decimal arithmetic.  (type percentage)
- Timestamps are stored in UTC.  (type timestamp)
- MUST enforce OWN-006 on every read and write of ENT-QuizAttempt: A student reads only their own attempts, attempts of their group, and attempts of a group they were a member of when the attempt was submitted, even after leaving it or its deletion (SCOPE-025); a teacher reads only attempts of quizzes in course spaces they own; an org admin never reads individual attempts, only aggregates (SEC-007)
- MUST enforce OWN-007 on every read and write of ENT-Answer: A student reads only answers of their own or their group's attempts; a teacher grades only answers of quizzes in course spaces they own; an org admin never reads individual answers (SEC-007)
- MUST enforce OWN-016 on every read and write of ENT-Question: A teacher reads, edits and deletes only their own bank questions (in_bank = true, owner_id = self); org admins and students have no access to any question bank (DATA-008)
- CMP-Activities MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Courses
- CMP-Assessment MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Courses, CMP-Activities
- CMP-Notifications MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Courses, CMP-Activities, CMP-Assessment
- MUST access EXT-002 only through Laravel Mail / Notification mail channel in CMP-Notifications
- MUST apply timeout 10s to every EXT-002 call; retries: 5, exponential backoff via queue (1m, 5m, 15m, 1h, 6h)

Standing constraints from deferred decisions (apply now):

- MUST read all configuration from environment variables  (OPS-001, deferred)
- MUST log structured JSON to stdout  (OPS-001, deferred)
- MUST access files only through Laravel Storage disks so an S3-compatible disk can replace the local one  (OPS-001, deferred)
- MUST NOT depend on provider-specific services in application code  (OPS-001, deferred)
- MUST keep live quiz state in PostgreSQL so a restart of Reverb or the queue worker loses no session  (OPS-001, deferred)
- MUST expose the Laravel health endpoint /up  (OPS-001, deferred)
- MUST NOT keep state on the server's local disk or memory: sessions, cache and queues in Redis, files through Storage disks  (OPS-001, deferred)
- MUST NOT keep state on the server's local disk or memory: sessions, cache and queues in Redis, files through Storage disks  (OPS-003, deferred)
- MUST record XP only as XpEvent ledger rows, never as a stored total  (SCOPE-018, deferred)
- MUST award XP from domain event listeners, not inside activity actions  (SCOPE-018, deferred)
- MUST mark every attribute holding personal data with sensitivity: personal in the model  (SEC-003, deferred)
- MUST NOT write personal data (emails, names, answers) to logs  (SEC-003, deferred)
- MUST reference users only by user_id in results, grades and analytics so users can be anonymised without breaking them  (SEC-003, deferred)
- MUST store a date of birth or minor flag per user  (SEC-003, deferred)
- MUST allow a user without a password (nullable password column) so SSO-only accounts are possible  (EXT-001, deferred)
- MUST resolve the organisation from the subdomain before authentication so login methods can be configured per organisation  (EXT-001, deferred)
- MUST NOT put provider-specific identity fields on the users table; external identities will live in their own table  (EXT-001, deferred)
- MUST send email only through Laravel Mail/Notifications, never a provider SDK directly  (EXT-002, deferred)
- MUST send email from queued jobs  (EXT-002, deferred)

## Acceptance criteria (each needs a passing test)

- [UC-022] Given a quiz with no questions, When the teacher publishes it, Then publishing is refused
- [UC-022] Given a live quiz, When the teacher adds a free-text question, Then it is refused
- [UC-022] Given a draft activity, Then no student can see it
- [UC-022] Given a teacher who leaves the wizard at step 3, When they return, Then the steps they completed are restored
- [UC-022] Given a published quiz with 1 submission, When the teacher changes a question's correct answer, Then it is refused
- [UC-022] Given a published quiz with 1 in-progress attempt and no submission, When the teacher removes a question or option, Then it is refused
- [UC-022] Given a new quiz in the wizard, Then it has 1 attempt, score visible immediately, answers visible after the deadline, no time limit, no negative marking and no pass mark
- [UC-022] Given a time limit of 0 or 1441 minutes or max_attempts of 0 or 101, When saved, Then it is refused
- [UC-023] Given a single-choice question with 2 correct options, When saved, Then it is refused
- [UC-023] Given a multiple-choice question with no correct option, When saved, Then it is refused
- [UC-023] Given a choice question with 1 or 11 options, When saved, Then it is refused
- [UC-023] Given a matching question with 1 pair or a pair with an empty side, When saved, Then it is refused
- [UC-023] Given a free-text question with options, When saved, Then it is refused
- [UC-023] Given points of 0, 100.01 or 1.005, When saved, Then it is refused
- [UC-023] Given a true/false question, When a teacher with the English UI and one with the Greek UI view it, Then they see 'True'/'False' and 'Σωστό'/'Λάθος' respectively, and the stored texts are 'true' and 'false'
- [UC-023] Given a choice question, When the teacher reorders its options by drag and drop and saves, Then the new order is kept
- [UC-023] Given teacher A's bank question, When teacher B opens, edits or deletes it, Then it is refused and nothing changes
- [UC-023] Given a teacher's bank question, When they delete it, Then it is gone from the bank
- [UC-012] Given a quiz allowing 2 attempts and a student with 2 submitted attempts, When they start another, Then it is refused
- [UC-012] Given an attempt with a 10-minute limit and 3 of 5 questions answered, When 10 minutes pass, Then the attempt is submitted with those 3 answers
- [UC-012] Given negative marking enabled with -0.25 per wrong answer on a 1-point question, When the answer is wrong, Then that question scores -0.25; given negative marking disabled, Then it scores 0
- [UC-012] Given default result visibility, When a student submits, Then they see their score immediately
- [UC-012] Given the timer is enforced on the server, When an answer arrives after the limit, Then it is not counted
- [UC-012] Given a 1-point multiple-choice question with 2 correct options of 4, When the student selects both correct and 1 wrong, Then it scores 0.50; When they select all 4, Then it scores 0
- [UC-012] Given a 1-point matching question with 3 pairs and 2 matched correctly, Then it scores 0.67
- [UC-012] Given negative marking of 25%, When a multiple-choice answer earns partial credit, Then no deduction applies; When a question is left unanswered, Then it scores 0
- [UC-012] Given negative marking and every answer wrong, When submitted, Then earned_points is 0
- [UC-012] Given a 30-minute limit and a deadline 10 minutes after the start, Then the attempt ends at the deadline
- [UC-012] Given a quiz with no time limit and no deadline and an attempt in progress, When the teacher closes the activity, Then the attempt is submitted
- [UC-012] Given a quiz without free-text questions, When submitted, Then the attempt is graded; given one with a free-text question, Then it stays submitted until that answer is graded
- [UC-012] Given a quiz allowing 2 attempts with 1 submitted and 1 in progress, When the student starts another, Then it is refused
- [UC-026] Given a 5-point free-text question, When the teacher enters 6, Then it is rejected
- [UC-026] Given an attempt with 2 free-text answers and 1 graded, Then the attempt remains provisional; When the 2nd is graded, Then it becomes final
- [UC-026] Given a teacher, When they grade an answer of another teacher's activity, Then it is refused
- [UC-017] Given a 0-10 scale and a score of 7 out of 8 points, Then the result shows 8.8
- [UC-017] Given a quiz with ungraded free-text answers, Then the result is marked provisional
- [UC-017] Given a student, When they request another student's result, Then it is refused
- [UC-028] Given an activity with 3 submissions of which 1 needs grading, When filtered by needs grading, Then exactly 1 is listed
- [UC-028] Given a teacher, When they open submissions of another teacher's activity, Then it is refused
- [UC-030] Given a valid file with one row of each of the five types, When confirmed, Then five bank questions owned by the teacher exist with the given options, correct flags, pairs and points
- [UC-030] Given a ';'-delimited file with points '2,5' and a UTF-8 BOM, When uploaded, Then the rows are valid and points are 2.50
- [UC-030] Given a file with 3 valid rows and 1 row breaking a DATA-016 rule, When uploaded, Then the report shows the invalid row's line number and error, and When confirmed, Then 3 are imported and 1 skipped
- [UC-030] Given a file where every row is invalid, When the report is shown, Then confirm is disabled and a confirm request imports nothing
- [UC-030] Given a Windows-1253 file, When uploaded, Then the whole file is refused with the save-as-CSV-UTF-8 message
- [UC-030] Given a file with a missing or an unknown header, When uploaded, Then the whole file is refused
- [UC-030] Given a file with 501 data rows, When uploaded, Then the whole file is refused
- [UC-030] Given teacher A's pending import, When teacher B or a teacher of another organisation confirms or cancels it, Then it is refused and nothing changes
- [UC-030] Given a pending import, When it is confirmed or cancelled, Then its file is deleted; Given a leftover file older than 24 hours, When the daily command runs, Then it is deleted
- [UC-030] Given a file changed so a row turns invalid between report and confirm, When confirmed, Then the row is re-validated and skipped
- [UC-030] Given the import page, When the template is downloaded, Then it has the header and one valid example row per type
- [OWN-006] Given a ROLE-Student user, When they read or modify an ENT-QuizAttempt record the rule does not grant them (A student reads only their own attempts, attempts of their group, and attempts of a group they were a member of when the attempt was submitted, even after leaving it or its deletion (SCOPE-025); a teacher reads only attempts of quizzes in course spaces they own; an org admin never reads individual attempts, only aggregates (SEC-007)), Then access is denied and nothing changes
- [OWN-006] Given a ROLE-Teacher user, When they read or modify an ENT-QuizAttempt record the rule does not grant them (A student reads only their own attempts, attempts of their group, and attempts of a group they were a member of when the attempt was submitted, even after leaving it or its deletion (SCOPE-025); a teacher reads only attempts of quizzes in course spaces they own; an org admin never reads individual attempts, only aggregates (SEC-007)), Then access is denied and nothing changes
- [OWN-006] Given a ROLE-OrgAdmin user, When they read or modify an ENT-QuizAttempt record the rule does not grant them (A student reads only their own attempts, attempts of their group, and attempts of a group they were a member of when the attempt was submitted, even after leaving it or its deletion (SCOPE-025); a teacher reads only attempts of quizzes in course spaces they own; an org admin never reads individual attempts, only aggregates (SEC-007)), Then access is denied and nothing changes
- [OWN-007] Given a ROLE-Student user, When they read or modify an ENT-Answer record the rule does not grant them (A student reads only answers of their own or their group's attempts; a teacher grades only answers of quizzes in course spaces they own; an org admin never reads individual answers (SEC-007)), Then access is denied and nothing changes
- [OWN-007] Given a ROLE-Teacher user, When they read or modify an ENT-Answer record the rule does not grant them (A student reads only answers of their own or their group's attempts; a teacher grades only answers of quizzes in course spaces they own; an org admin never reads individual answers (SEC-007)), Then access is denied and nothing changes
- [OWN-007] Given a ROLE-OrgAdmin user, When they read or modify an ENT-Answer record the rule does not grant them (A student reads only answers of their own or their group's attempts; a teacher grades only answers of quizzes in course spaces they own; an org admin never reads individual answers (SEC-007)), Then access is denied and nothing changes
- [OWN-016] Given a ROLE-Teacher user, When they read or modify an ENT-Question record the rule does not grant them (A teacher reads, edits and deletes only their own bank questions (in_bank = true, owner_id = self); org admins and students have no access to any question bank (DATA-008)), Then access is denied and nothing changes
- [OWN-016] Given a ROLE-OrgAdmin user, When they read or modify an ENT-Question record the rule does not grant them (A teacher reads, edits and deletes only their own bank questions (in_bank = true, owner_id = self); org admins and students have no access to any question bank (DATA-008)), Then access is denied and nothing changes
- [OWN-016] Given a ROLE-Student user, When they read or modify an ENT-Question record the rule does not grant them (A teacher reads, edits and deletes only their own bank questions (in_bank = true, owner_id = self); org admins and students have no access to any question bank (DATA-008)), Then access is denied and nothing changes
- [ENT-Activity] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [ENT-QuizAttempt] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [ENT-Answer] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [INT-001] Given EXT-002 is unavailable or times out, Then: Emails stay queued and are retried; no user flow is blocked; the in-app notification is created regardless; after the last retry an invitation shows 'email not delivered' to the admin, who can resend it

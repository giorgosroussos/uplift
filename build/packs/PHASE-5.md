<!-- GENERATED — do not edit -->
# Pack PHASE-5 — Live quiz end to end: lobby or scheduled start, common time window, live monitoring, reconnection, final ranking

_design v1.1.0 · c01395b7b2ab_

Depends on: PHASE-4 · environment: development · components: CMP-Live, CMP-Assessment

## Tasks

| task | title | depends on | writes |
|---|---|---|---|
| P5-T1 | Live mode in the wizard | — | CMP-Assessment |
| P5-T2 | Live session engine | P5-T1 | CMP-Live |
| P5-T3 | Real-time channels and reconnection | P5-T2 | CMP-Live |
| P5-T4 | Teacher monitor UI | P5-T3 | CMP-Live |
| P5-T5 | Student player UI (mobile-first) | P5-T3 | CMP-Live |
| P5-T6 | Live quiz load browser test | P5-T4, P5-T5 | CMP-Live |

Execution waves: [P5-T1] → [P5-T2] → [P5-T3] → [P5-T4] → [P5-T5] → [P5-T6]

## Out of scope (do not build)

- XP awarding (PHASE-6 listens to LiveSessionFinished)
- Per-question pacing, reveal, live leaderboard during the quiz, projector mode (SCOPE-021)

## Use cases

#### UC-024 — Run live quiz (must)

Actors: ACT-Teacher · Goal: Run a quiz that all enrolled students take at the same time within one common time window, monitor progress live and show the final ranking

Main flow:
1. Teacher prepares the quiz like any other activity and chooses the start mode: manual (lobby, teacher presses Start) or scheduled (starts automatically at a set date and time)
2. Manual mode: the lobby shows enrolled students as they join; the teacher presses Start
3. At start the common end time is fixed: start time + quiz duration
4. While running, the teacher sees live progress: who joined, how many questions each student has answered, who has submitted
5. At the common end time (or when every joined student has submitted) the quiz finishes, answers are scored and the final ranking with podium is shown to teacher and students

Exceptions:
- Manual mode, teacher closes the lobby without starting: the quiz is not considered run and can be started later
- Teacher disconnects: the quiz continues and finishes at the common end time
- Leaderboards disabled for the organisation: no podium or ranking is shown; each student sees only their own score
- Quiz already run: it cannot be started again (SCOPE-019)

#### UC-025 — Play live quiz (must)

Actors: ACT-Student · Goal: Take a live quiz together with the class within the common time window, moving freely between questions, and see results and ranking at the end

Main flow:
1. Student, logged in and enrolled in the course space, opens the live quiz and waits until it starts
2. Moves freely between questions and answers or changes answers; every change is saved
3. Submits early, or the quiz is submitted automatically at the common end time
4. When the quiz finishes, sees which answers were correct, own score, final ranking with podium (if enabled) and XP earned

Exceptions:
- Not enrolled: join refused (SEC-005)
- Joins late: has only the time left until the common end time
- Connection lost: saved answers are kept; on reconnection the student continues with the remaining time
- After submitting: answers can no longer be changed

## Entities

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

#### ENT-LiveSession — The real-time run of a live quiz with one common end time (SCOPE-021); at most one non-cancelled session per quiz (SCOPE-022)

Owned by CMP-Live · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| quiz_id | uuid | uuid | → ENT-Quiz, required |
| host_id | uuid | uuid | → ENT-User, required |
| status | enum | string | required |
| started_at | timestamp | timestamptz |  |
| ends_at | timestamp | timestamptz |  |
| finished_at | timestamp | timestamptz |  |

Relations: one-to-many → ENT-LiveParticipant (on delete cascade)

States: initial `lobby`; allowed transitions: `lobby -> running`, `lobby -> cancelled`, `running -> finished`

#### ENT-LiveParticipant — A student in a live session

Owned by CMP-Live · retention: Kept with the session; follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| live_session_id | uuid | uuid | → ENT-LiveSession, required |
| student_id | uuid | uuid | → ENT-User, required |
| joined_at | timestamp | timestamptz | required |
| submitted_at | timestamp | timestamptz |  |
| score | decimal | decimal | personal |
| final_rank | integer | integer |  |

Relations: one-to-many → ENT-LiveAnswer (on delete cascade)

#### ENT-LiveAnswer — Current answer of a participant to one question; may change until submission or the end time; scored when the session finishes

Owned by CMP-Live · retention: As ENT-LiveParticipant · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| live_participant_id | uuid | uuid | → ENT-LiveParticipant, required |
| quiz_question_id | uuid | uuid | → ENT-QuizQuestion, required |
| response | json | jsonb | personal |
| is_correct | boolean | boolean |  |
| points | decimal | decimal |  |
| answered_at | timestamp | timestamptz | required |

## Constraints

- MUST reject free-text questions in a live quiz  (SCOPE-019)
- MUST NOT start a second live session for the same quiz  (SCOPE-019)
- MUST fix one common end time for a live session at its start and enforce it on the server  (SCOPE-021)
- MUST NOT reveal correctness of any live answer before the session finishes  (SCOPE-021)
- MUST finish and score a live session at its end time without teacher action  (SCOPE-021)
- MUST allow at most one non-cancelled live session per quiz  (SCOPE-022)
- MUST NOT apply negative marking in live quizzes  (SCOPE-022)
- MUST reject a live quiz join from a user who is not enrolled in the quiz's course space  (SEC-005)
- MUST NOT offer anonymous or PIN-only participation  (SEC-005)
- MUST show only nickname and avatar on leaderboards  (SEC-008)
- MUST scope leaderboards to one course space  (SEC-008)
- MUST hash passwords with argon2id  (SEC-010)
- MUST require passwords of at least 8 characters that pass Password::uncompromised()  (SEC-010)
- MUST scope session cookies to the organisation subdomain (host-only)  (SEC-010)
- MUST invalidate other sessions when a password changes  (SEC-010)
- MUST use only private or presence broadcast channels, authorised by the same Policies, with the organisation id in the channel name  (SEC-010)
- MUST NOT send correct answers or correctness to students before the live session finishes  (SEC-010)
- MUST NOT broadcast personal data other than nickname and avatar  (SEC-010)
- MUST rate-limit login to 5 attempts per minute per email  (SEC-010)
- MUST rate-limit enrolment key attempts to 10 per minute per user  (SEC-010)
- MUST rate-limit organisation registration to 3 per hour per IP  (SEC-010)
- MUST write an audit entry for grade changes, role changes, user deactivation, organisation settings changes and platform admin actions  (SEC-010)
- MUST accept logo uploads only as png, jpg or sanitised svg up to 2 MB and CSV uploads up to 1 MB, stored outside the public directory  (SEC-010)
- MUST run composer audit and npm audit in CI  (SEC-010)
- MUST submit live answers through HTTP endpoints, never websocket client events (whisper)  (ARCH-006)
- MUST derive client countdowns from server-sent ends_at and server_now  (ARCH-006)
- MUST fall back to HTTP polling every 2 seconds when the websocket connection is lost  (ARCH-006)
- MUST log external calls with ids only, never emails or names  (ARCH-006)
- MUST make the deploy script refuse or wait while any live session has status running  (OPS-002)
- MUST reject ENT-LiveSession state transitions that are not listed in its state model
- MUST NOT write ENT-LiveParticipant fields score to logs or error messages
- MUST NOT write ENT-LiveAnswer fields response to logs or error messages
- Percentages MUST use exact decimal arithmetic.  (type percentage)
- Timestamps are stored in UTC.  (type timestamp)
- MUST enforce OWN-012 on every read and write of ENT-LiveParticipant: A student sees only their own progress and score, plus the final ranking (nicknames only, SEC-008) when enabled; the host teacher sees all participants of their session
- MUST enforce OWN-013 on every read and write of ENT-LiveAnswer: A student sees only their own live answers; during the session the host teacher sees only how many questions each participant has answered, after it finishes the scores and answer distributions of their own session
- CMP-Live MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Courses, CMP-Activities, CMP-Assessment
- CMP-Assessment MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Courses, CMP-Activities

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

- [UC-024] Given a manual-start live quiz with 15 minutes duration started at 10:00:00, Then its end time is 10:15:00 for every student
- [UC-024] Given a scheduled live quiz at 10:00, When the clock reaches 10:00, Then it starts without teacher action
- [UC-024] Given 50 joined students, When a student answers a question, Then the teacher's progress view reflects it within 1 second
- [UC-024] Given a running quiz, When the teacher closes their browser, Then the quiz still finishes at its end time and is scored
- [UC-024] Given a finished live quiz, When the teacher tries to start it again, Then it is refused
- [UC-024] Given a running quiz with 30 joined students, When the 30th submits before the end time, Then the quiz finishes immediately
- [UC-024] Given a lobby closed without starting, When the teacher opens a new lobby for the same quiz, Then it is allowed
- [UC-024] Given a 3-point multiple-choice question with 3 correct options and a student who selected 2 of them, Then they earn 2 points
- [UC-024] Given leaderboards are disabled for the organisation, Then neither teacher nor students see a ranking or podium
- [UC-025] Given a student not enrolled in the course space, When they try to join, Then it is refused
- [UC-025] Given a quiz ending at 10:15:00, When a student joins at 10:10:00, Then they have 5 minutes
- [UC-025] Given a student who answered question 2 with B, When they go back and change it to C before submitting, Then C is the scored answer
- [UC-025] Given a student who submitted early, When they try to change an answer, Then it is refused
- [UC-025] Given a running quiz, Then the student does not see whether any answer is correct until the quiz finishes
- [UC-025] Given an answer arriving after the common end time, Then it is not counted
- [UC-025] Given a finished live quiz, Then the student gains XP and no grade is added to My results
- [OWN-012] Given a ROLE-Student user, When they read or modify an ENT-LiveParticipant record the rule does not grant them (A student sees only their own progress and score, plus the final ranking (nicknames only, SEC-008) when enabled; the host teacher sees all participants of their session), Then access is denied and nothing changes
- [OWN-012] Given a ROLE-Teacher user, When they read or modify an ENT-LiveParticipant record the rule does not grant them (A student sees only their own progress and score, plus the final ranking (nicknames only, SEC-008) when enabled; the host teacher sees all participants of their session), Then access is denied and nothing changes
- [OWN-013] Given a ROLE-Student user, When they read or modify an ENT-LiveAnswer record the rule does not grant them (A student sees only their own live answers; during the session the host teacher sees only how many questions each participant has answered, after it finishes the scores and answer distributions of their own session), Then access is denied and nothing changes
- [OWN-013] Given a ROLE-Teacher user, When they read or modify an ENT-LiveAnswer record the rule does not grant them (A student sees only their own live answers; during the session the host teacher sees only how many questions each participant has answered, after it finishes the scores and answer distributions of their own session), Then access is denied and nothing changes
- [ENT-LiveSession] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [phase] A browser test with 50 simulated students shows each answer in the teacher's progress view within 1 second

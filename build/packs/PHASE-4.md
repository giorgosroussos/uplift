<!-- GENERATED — do not edit -->
# Pack PHASE-4 — The other four activity types and groups: polls, topic selection, topic proposals with approval, slot booking, group activities

_design v2.1.0 · dcadfdfa5719_

Depends on: PHASE-3 · environment: development · components: CMP-Courses, CMP-Activities, CMP-Assessment, CMP-Notifications

## Tasks

| task | title | depends on | writes |
|---|---|---|---|
| P4-T1 | Groups | — | CMP-Courses |
| P4-T2 | Polls | — | CMP-Activities |
| P4-T3 | Topic selection | P4-T1, P4-T2 | CMP-Activities |
| P4-T4 | Topic proposals and approval | P4-T3 | CMP-Activities |
| P4-T5 | Slot booking | P4-T1, P4-T4 | CMP-Activities |
| P4-T6 | Group quiz attempts | P4-T1 | CMP-Assessment |
| P4-T7 | Notifications for topic decisions and group invitations | P4-T1, P4-T4 | CMP-Notifications |

Execution waves: [P4-T1, P4-T2] → [P4-T3, P4-T6] → [P4-T4] → [P4-T5, P4-T7]

## Out of scope (do not build)

- Live quizzes (PHASE-5)
- XP and badges (PHASE-6)
- Analytics (PHASE-7)

## Use cases

#### UC-019 — Manage groups (should)

Actors: ACT-Student · Goal: Create a group, invite members, accept or decline invitations for group activities

#### UC-013 — Vote in poll (must)

Actors: ACT-Student · Goal: Choose one or more options in an anonymous poll and see results as configured

Main flow:
1. Student opens an open poll in an enrolled course space
2. Selects one option, or up to the allowed number of options
3. Submits the vote; it cannot be changed
4. Sees results after voting or after the poll closes, as set by the teacher

Exceptions:
- Already voted: a second vote is refused
- Poll closed: voting refused

#### UC-014 — Select assignment topic (must)

Actors: ACT-Student · Goal: Pick an available topic from the teacher's list, individually or for the group

Main flow:
1. Student opens the topic list; each topic shows remaining capacity
2. Selects a topic with free capacity (for the group in group activities)
3. May change or cancel the selection until the deadline

Exceptions:
- Topic full: selection refused
- Deadline passed: change or cancel refused

#### UC-015 — Propose assignment topic (must)

Actors: ACT-Student · Goal: Propose a topic title and description and follow its approval status

Main flow:
1. Student (or one member for the group) submits a title and description before the deadline
2. Proposal status is pending until the teacher decides
3. Student sees approved or rejected with the teacher's comment

Exceptions:
- Rejected: the student may submit a new proposal before the deadline
- Pending proposal exists: a second concurrent proposal is refused

#### UC-027 — Approve proposed topics (must)

Actors: ACT-Teacher · Goal: Approve or reject student-proposed assignment topics

Main flow:
1. Teacher opens pending proposals of an activity
2. Approves, or rejects with a comment
3. The student or group is notified

#### UC-016 — Book presentation or exam slot (must)

Actors: ACT-Student · Goal: Pick and confirm an available time slot

Main flow:
1. Student sees slots in a calendar or list with remaining capacity
2. Books a slot with free capacity (for the group in group activities)
3. May change or cancel the booking until the deadline

Exceptions:
- Slot full: booking refused
- Deadline passed: change or cancel refused

## Entities

#### ENT-Group — A student group in a course space, used for group activities

Owned by CMP-Courses · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| course_space_id | uuid | uuid | → ENT-CourseSpace, required |
| name | string | string(255) | required |
| created_by | uuid | uuid | → ENT-User, required |

Relations: one-to-many → ENT-GroupMembership (on delete cascade)

#### ENT-GroupMembership — Membership or pending invitation of a student in a group

Owned by CMP-Courses · retention: Kept with the group; follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| group_id | uuid | uuid | → ENT-Group, required |
| student_id | uuid | uuid | → ENT-User, required |
| status | enum | string | required |

States: initial `invited`; allowed transitions: `invited -> member`, `invited -> declined`, `member -> left`

#### ENT-Poll — Poll subtype of Activity (anonymous, SCOPE-013)

Owned by CMP-Activities · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| activity_id | uuid | uuid | → ENT-Activity, required |
| max_choices | integer | integer | required |
| results_visibility | enum | string | required |

Relations: one-to-many → ENT-PollOption (on delete cascade); one-to-many → ENT-PollParticipation (on delete cascade)

#### ENT-PollOption — An option of a poll with its vote count source

Owned by CMP-Activities · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| poll_id | uuid | uuid | → ENT-Poll, required |
| label | string | string(255) | required |
| position | integer | integer | required |

Relations: one-to-many → ENT-PollVote (on delete cascade)

#### ENT-PollParticipation — Records THAT a student voted, never what (DATA-006)

Owned by CMP-Activities · retention: Kept with the poll; follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| poll_id | uuid | uuid | → ENT-Poll, required |
| student_id | uuid | uuid | → ENT-User, required |

#### ENT-PollVote — One chosen option; carries no user reference (DATA-006)

Owned by CMP-Activities · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| poll_option_id | uuid | uuid | → ENT-PollOption, required |

#### ENT-TopicActivity — Subtype of Activity for topic selection and topic proposal

Owned by CMP-Activities · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| activity_id | uuid | uuid | → ENT-Activity, required |

Relations: one-to-many → ENT-Topic (on delete cascade); one-to-many → ENT-TopicProposal (on delete cascade)

#### ENT-Topic — A topic offered for selection, with capacity (SCOPE-011)

Owned by CMP-Activities · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| topic_activity_id | uuid | uuid | → ENT-TopicActivity, required |
| title | string | string(255) | required |
| description | text | text |  |
| capacity | integer | integer | required |

Relations: one-to-many → ENT-TopicChoice (on delete cascade)

#### ENT-TopicChoice — A student's or group's current topic; one per activity

Owned by CMP-Activities · retention: Kept with the activity; follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| topic_id | uuid | uuid | → ENT-Topic, required |
| student_id | uuid | uuid | → ENT-User |
| group_id | uuid | uuid | → ENT-Group |

#### ENT-TopicProposal — A topic proposed by a student or group

Owned by CMP-Activities · retention: Kept with the activity; follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| topic_activity_id | uuid | uuid | → ENT-TopicActivity, required |
| student_id | uuid | uuid | → ENT-User |
| group_id | uuid | uuid | → ENT-Group |
| title | string | string(255) | required |
| description | text | text | personal |
| status | enum | string | required |
| teacher_comment | text | text |  |

States: initial `pending`; allowed transitions: `pending -> approved`, `pending -> rejected`

#### ENT-SlotActivity — Slot booking subtype of Activity

Owned by CMP-Activities · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| activity_id | uuid | uuid | → ENT-Activity, required |
| slot_duration | duration | integer (seconds) |  |

Relations: one-to-many → ENT-Slot (on delete cascade)

#### ENT-Slot — A bookable time slot with capacity

Owned by CMP-Activities · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| slot_activity_id | uuid | uuid | → ENT-SlotActivity, required |
| starts_at | timestamp | timestamptz | required |
| ends_at | timestamp | timestamptz | required |
| location | string | string(255) |  |
| capacity | integer | integer | required |

Relations: one-to-many → ENT-Booking (on delete cascade)

#### ENT-Booking — A student's or group's booking; one per activity

Owned by CMP-Activities · retention: Kept with the activity; follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| slot_id | uuid | uuid | → ENT-Slot, required |
| student_id | uuid | uuid | → ENT-User |
| group_id | uuid | uuid | → ENT-Group |

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
- `TopicProposalDecided(proposal_id)`
- `GroupInvitation(group_membership_id)`

Failure policy: timeout 10s, retries 5, exponential backoff via queue (1m, 5m, 15m, 1h, 6h), idempotency key —; when unavailable: Emails stay queued and are retried; no user flow is blocked; the in-app notification is created regardless; after the last retry an invitation shows 'email not delivered' to the admin, who can resend it

## External systems in `development`

| id | name | mode | tool | config vars |
|---|---|---|---|---|
| EXT-002 | Email delivery | catcher | Mailpit | MAIL_* |

## Constraints

- MUST NOT allow more holders of a topic or slot than its capacity, also under concurrent requests  (SCOPE-011)
- MUST NOT expose to teachers or admins which student cast which vote  (SCOPE-013)
- MUST NOT expose the list of participants of a poll to teachers or admins  (SEC-009)
- MUST NOT store a user reference on poll choices  (DATA-006)
- MUST record poll participation separately to prevent a second vote  (DATA-006)
- MUST reject ENT-GroupMembership state transitions that are not listed in its state model
- MUST reject ENT-TopicProposal state transitions that are not listed in its state model
- MUST NOT write ENT-TopicProposal fields description to logs or error messages
- Timestamps are stored in UTC.  (type timestamp)
- MUST enforce OWN-005 on every read and write of ENT-GroupMembership: A student sees memberships only of groups they belong to or are invited to; a teacher sees groups only of course spaces they own
- MUST enforce OWN-008 on every read and write of ENT-TopicChoice: A student changes only their own or their group's topic choice; a teacher sees choices only of their own activities
- MUST enforce OWN-009 on every read and write of ENT-TopicProposal: A student reads only their own or their group's proposals; a teacher decides only proposals of their own activities
- MUST enforce OWN-010 on every read and write of ENT-Booking: A student changes only their own or their group's booking; a teacher sees bookings only of their own activities
- MUST enforce OWN-011 on every read and write of ENT-PollParticipation: A student sees only their own participation; a teacher sees only vote counts per option and the number of participants, never who voted or what anyone voted
- CMP-Courses MUST only depend on: CMP-Tenancy, CMP-Identity
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

- [UC-013] Given a student who has voted, When they vote again, Then it is refused and the tally is unchanged
- [UC-013] Given a poll with results after closing, When a student has voted and the poll is still open, Then results are not shown to them
- [UC-013] Given 3 votes on option A and 1 on B, When results are shown, Then A has 3 and B has 1 and no voter identity is shown to anyone, including the teacher and org admin
- [UC-013] Given a poll allowing at most 2 options, When a student selects 3, Then the vote is rejected
- [UC-014] Given a topic with capacity 1 and two students selecting it at the same time, Then exactly one selection succeeds
- [UC-014] Given a student holding topic A before the deadline, When they switch to topic B with free capacity, Then they hold B and A regains 1 free place
- [UC-014] Given the deadline has passed, When a student cancels their topic, Then it is refused
- [UC-014] Given a group activity, When one member selects a topic, Then all members see the topic as their group's selection
- [UC-015] Given a rejected proposal and the deadline not passed, When the student proposes again, Then a new pending proposal is created
- [UC-015] Given a pending proposal, When the student submits another, Then it is refused
- [UC-015] Given an approved proposal, Then the student cannot submit another proposal for that activity
- [UC-027] Given a pending proposal, When the teacher rejects it without a comment, Then it is refused
- [UC-027] Given a pending proposal, When approved, Then its status is approved and the student is notified
- [UC-016] Given a slot with capacity 2 holding 2 bookings, When a third student books it, Then it is refused
- [UC-016] Given a student with a booking, When they book another slot of the same activity, Then the first booking is released (one booking per student or group)
- [UC-016] Given two concurrent bookings for the last place of a slot, Then exactly one succeeds
- [OWN-005] Given a ROLE-Student user, When they read or modify an ENT-GroupMembership record the rule does not grant them (A student sees memberships only of groups they belong to or are invited to; a teacher sees groups only of course spaces they own), Then access is denied and nothing changes
- [OWN-005] Given a ROLE-Teacher user, When they read or modify an ENT-GroupMembership record the rule does not grant them (A student sees memberships only of groups they belong to or are invited to; a teacher sees groups only of course spaces they own), Then access is denied and nothing changes
- [OWN-008] Given a ROLE-Student user, When they read or modify an ENT-TopicChoice record the rule does not grant them (A student changes only their own or their group's topic choice; a teacher sees choices only of their own activities), Then access is denied and nothing changes
- [OWN-008] Given a ROLE-Teacher user, When they read or modify an ENT-TopicChoice record the rule does not grant them (A student changes only their own or their group's topic choice; a teacher sees choices only of their own activities), Then access is denied and nothing changes
- [OWN-009] Given a ROLE-Student user, When they read or modify an ENT-TopicProposal record the rule does not grant them (A student reads only their own or their group's proposals; a teacher decides only proposals of their own activities), Then access is denied and nothing changes
- [OWN-009] Given a ROLE-Teacher user, When they read or modify an ENT-TopicProposal record the rule does not grant them (A student reads only their own or their group's proposals; a teacher decides only proposals of their own activities), Then access is denied and nothing changes
- [OWN-010] Given a ROLE-Student user, When they read or modify an ENT-Booking record the rule does not grant them (A student changes only their own or their group's booking; a teacher sees bookings only of their own activities), Then access is denied and nothing changes
- [OWN-010] Given a ROLE-Teacher user, When they read or modify an ENT-Booking record the rule does not grant them (A student changes only their own or their group's booking; a teacher sees bookings only of their own activities), Then access is denied and nothing changes
- [OWN-011] Given a ROLE-Student user, When they read or modify an ENT-PollParticipation record the rule does not grant them (A student sees only their own participation; a teacher sees only vote counts per option and the number of participants, never who voted or what anyone voted), Then access is denied and nothing changes
- [OWN-011] Given a ROLE-Teacher user, When they read or modify an ENT-PollParticipation record the rule does not grant them (A student sees only their own participation; a teacher sees only vote counts per option and the number of participants, never who voted or what anyone voted), Then access is denied and nothing changes
- [ENT-GroupMembership] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [ENT-TopicProposal] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [INT-001] Given EXT-002 is unavailable or times out, Then: Emails stay queued and are retried; no user flow is blocked; the in-app notification is created regardless; after the last retry an invitation shows 'email not delivered' to the admin, who can resend it

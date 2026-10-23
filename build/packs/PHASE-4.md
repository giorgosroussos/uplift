<!-- GENERATED — do not edit -->
# Pack PHASE-4 — The other four activity types and groups: polls, topic selection, topic proposals with approval, slot booking, group activities

_design v3.2.0 · 626aadb4b16e_

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

Actors: ACT-Student, ACT-Teacher, ACT-OrgAdmin · Goal: Create a group, invite members, accept or decline invitations for group activities

Main flow:
1. An enrolled student creates a group in a course space with a name; they become its owner and its first member (SCOPE-025)
2. Any member invites students enrolled in the same course space; the invitee is notified
3. The invitee accepts (becomes a member) or declines; any member may withdraw a pending invitation
4. The owner renames the group, transfers ownership to another member, or dissolves the group
5. A member leaves; the owner leaves only after transferring ownership; when the last member leaves the group is deleted
6. The teacher who owns the course space, and org admins, view its groups with members and pending invitations, read-only

Exceptions:
- Invitee not enrolled in the course space, already a member or already invited: the invitation is refused
- Owner leaves while other members remain: refused until ownership is transferred
- A student who is not a member renames, invites, transfers or dissolves: refused
- A member is unenrolled from the course space: their membership becomes left and their pending invitations withdrawn; an unenrolled owner's ownership passes to the member with the earliest joined_at

#### UC-013 — Vote in poll (must)

Actors: ACT-Student · Goal: Choose one or more options in an anonymous poll and see results as configured

Main flow:
1. Student opens an open poll in an enrolled course space
2. Selects one option, or up to the allowed number of options
3. Submits the vote; it cannot be changed
4. Sees results after voting or after the poll closes, as set by the teacher; after closing every enrolled student sees them (SCOPE-026)

Exceptions:
- Already voted: a second vote is refused
- Poll closed (activity closed or deadline passed): voting refused

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
| owner_id | uuid | uuid | → ENT-User, required |
| deleted_at | timestamp | timestamptz |  |

Relations: one-to-many → ENT-GroupMembership (on delete cascade)

#### ENT-GroupMembership — Membership or pending invitation of a student in a group; a new row per invitation, old rows kept as history (SCOPE-025)

Owned by CMP-Courses · retention: Kept with the group; follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| group_id | uuid | uuid | → ENT-Group, required |
| student_id | uuid | uuid | → ENT-User, required |
| status | enum | string | required |
| invited_by | uuid | uuid | → ENT-User |
| joined_at | timestamp | timestamptz |  |
| left_at | timestamp | timestamptz |  |

States: initial `invited`; allowed transitions: `invited -> member`, `invited -> declined`, `invited -> withdrawn`, `member -> left`

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

Owned by CMP-Activities · retention: Kept with the poll; deleted with the student's account (on delete cascade), the anonymous votes stay (SCOPE-026) · audit: no

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
- `GradeChanged(attempt_id)`
- `TopicProposalDecided(proposal_id)`
- `GroupInvitation(group_membership_id), on GroupInvitationCreated (SCOPE-025)`

Failure policy: timeout 10s, retries 5, exponential backoff via queue (1m, 5m, 15m, 1h, 6h), idempotency key —; when unavailable: Emails stay queued and are retried; no user flow is blocked; the in-app notification is created regardless; after the last retry an invitation shows 'email not delivered' to the admin, who can resend it

## External systems in `development`

| id | name | mode | tool | config vars |
|---|---|---|---|---|
| EXT-002 | Email delivery | catcher | Mailpit | MAIL_* |

## Constraints

- MUST NOT allow more holders of a topic or slot than its capacity, also under concurrent requests  (SCOPE-011)
- MUST NOT expose to teachers or admins which student cast which vote  (SCOPE-013)
- MUST allow only students enrolled in the group's course space to create, be invited to or accept membership of a group  (SCOPE-025)
- MUST allow a student to be a member of several groups in the same course space  (SCOPE-025)
- MUST create the creator's membership as member with joined_at and set them as owner_id  (SCOPE-025)
- MUST keep at most one invited or member row per (organization_id, group_id, student_id) with a partial unique index; re-invitation creates a new row  (SCOPE-025)
- MUST let only members invite and withdraw invitations, and only the owner rename, transfer ownership or dissolve  (SCOPE-025)
- MUST refuse the owner leaving while other members remain  (SCOPE-025)
- MUST soft-delete a group when its owner dissolves it or it has no members left, and set its pending invitations to withdrawn  (SCOPE-025)
- MUST NOT delete or alter attempts, topic choices, proposals or bookings of a deleted group  (SCOPE-025)
- MUST set joined_at when a membership becomes member and left_at when it becomes left  (SCOPE-025)
- MUST let a student read a group attempt and its grade if they were a member of the group when it was submitted, even after leaving or the group's deletion  (SCOPE-025)
- MUST, when an enrolment is deleted, set the student's memberships in that space to left and their pending invitations to withdrawn in the same transaction, passing ownership to the member with the earliest joined_at  (SCOPE-025)
- MUST let the acting student choose which of their groups acts in a group activity, and refuse a group whose current member already takes part in that activity through another group  (SCOPE-025)
- MUST check ENT-Activity.group_size only when a group acts in an activity, not at group level  (SCOPE-025)
- MUST give teachers and org admins read-only access to groups (OWN-005)  (SCOPE-025)
- MUST dispatch App\Modules\Courses\Events\GroupInvitationCreated(organizationId, groupMembershipId) and GroupMembershipChanged(organizationId, groupMembershipId, fromStatus, toStatus) after commit  (SCOPE-025)
- MUST require 2-20 options per poll with labels of 1-255 characters, unique within the poll ignoring case  (SCOPE-026)
- MUST keep max_choices within 1-20 and refuse publishing with max_choices above the number of options  (SCOPE-026)
- MUST default a new poll to max_choices 1 and results_visibility after_vote  (SCOPE-026)
- MUST show the owning teacher the votes per option and the number of participants at any time, and never the participants  (SCOPE-026)
- MUST NOT show a poll to other teachers or to org admins  (SCOPE-026)
- MUST show results to every enrolled student once the poll is closed  (SCOPE-026)
- MUST treat a poll as closed when its activity is closed or its deadline has passed, and refuse votes from then on  (SCOPE-026)
- MUST refuse, once a vote exists, changes to max_choices and to the set or labels of options with 409 problem details  (SCOPE-026)
- MUST delete a user's poll participations with the account and keep the anonymous votes  (SCOPE-026)
- MUST NOT expose the list of participants of a poll to teachers or admins  (SEC-009)
- MUST NOT store a user reference on poll choices  (DATA-006)
- MUST record poll participation separately to prevent a second vote  (DATA-006)
- MUST reject ENT-GroupMembership state transitions that are not listed in its state model
- MUST reject ENT-TopicProposal state transitions that are not listed in its state model
- MUST NOT write ENT-TopicProposal fields description to logs or error messages
- Timestamps are stored in UTC.  (type timestamp)
- MUST enforce OWN-005 on every read and write of ENT-GroupMembership: A student sees memberships only of groups they belong to or are invited to; only members change a group (invite, withdraw) and only its owner renames, transfers or dissolves it; a teacher sees groups only of course spaces they own, read-only; an org admin sees groups of any course space of their organisation, read-only
- MUST enforce OWN-008 on every read and write of ENT-TopicChoice: A student changes only their own or their group's topic choice; a teacher sees choices only of their own activities
- MUST enforce OWN-009 on every read and write of ENT-TopicProposal: A student reads only their own or their group's proposals; a teacher decides only proposals of their own activities
- MUST enforce OWN-010 on every read and write of ENT-Booking: A student changes only their own or their group's booking; a teacher sees bookings only of their own activities
- MUST enforce OWN-011 on every read and write of ENT-PollParticipation: A student sees only their own participation; the owning teacher sees vote counts per option and the number of participants at any time, never who voted or what anyone voted; other teachers and org admins do not see the poll (SCOPE-026)
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

- [UC-019] Given an enrolled student, When they create group G in space S, Then G exists with them as owner and their membership is member with joined_at set
- [UC-019] Given a student who is a member of a group in S, When they create or accept a second group in S, Then it succeeds (several groups per space are allowed)
- [UC-019] Given a student not enrolled in S, When they create a group in S or are invited to a group of S, Then it is refused
- [UC-019] Given a member of G, When they open the invite dialog, Then only students enrolled in S who are neither members of nor invited to G are offered
- [UC-019] Given member M of G, When M invites enrolled student X, Then an invited membership exists and one GroupInvitationCreated is dispatched after commit
- [UC-019] Given X is already invited to or a member of G, When a member invites X again, Then it is refused
- [UC-019] Given X declined, left or was withdrawn from G, When a member invites X again, Then a new invited row is created and the old row is unchanged
- [UC-019] Given a pending invitation, When X accepts, Then the row becomes member with joined_at set and GroupMembershipChanged(invited, member) is dispatched; When X declines instead, Then it becomes declined
- [UC-019] Given member M of G, When M leaves, Then M's row has status left and left_at set
- [UC-019] Given a pending invitation, When any member of G withdraws it, Then it becomes withdrawn and X can no longer accept it
- [UC-019] Given owner O and member M, When O transfers ownership to M, Then M is owner; When a non-owner tries to transfer, rename or dissolve, Then it is refused
- [UC-019] Given owner O with other members, When O leaves, Then it is refused; When a non-owner member leaves, Then their row becomes left
- [UC-019] Given G with one member, When that member leaves, Then G is soft-deleted, its pending invitations become withdrawn and it is absent for students, teacher and org admin
- [UC-019] Given owner O, When O dissolves G, Then G is soft-deleted, its pending invitations become withdrawn, and its attempts, topic choices, proposals and bookings remain
- [UC-019] Given owner O of G with members A (joined first) and B, When O is unenrolled from S, Then O's row becomes left and A is owner
- [UC-019] Given space S with groups, When its teacher archives S, Then every membership in S becomes left, every pending invitation withdrawn and every group soft-deleted
- [UC-019] Given the teacher who owns S, When they open S's groups, Then they see each group with members and pending invitations and cannot change them; When another teacher opens them, Then it is refused
- [UC-019] Given an org admin, When they open the groups of any space of their organisation, Then they see them read-only
- [UC-019] Given student X not in G and not invited to G, When X requests G's memberships, Then it is refused
- [UC-019] Given a group of organisation A, When a user of organisation B requests it, Then it is not found
- [UC-013] Given a student who has voted, When they vote again, Then it is refused and the tally is unchanged
- [UC-013] Given a poll with results after closing, When a student has voted and the poll is still open, Then results are not shown to them
- [UC-013] Given 3 votes on option A and 1 on B, When results are shown, Then A has 3 and B has 1 and no voter identity is shown to anyone, including the teacher
- [UC-013] Given a poll allowing at most 2 options, When a student selects 3, Then the vote is rejected
- [UC-013] Given a poll with 1 option, or 21 options, or two options whose labels differ only in case, When the teacher saves or publishes it, Then it is refused
- [UC-013] Given a poll with 3 options and max_choices 4, When the teacher publishes it, Then it is refused
- [UC-013] Given a new poll in the wizard, Then max_choices is 1 and results_visibility is after_vote
- [UC-013] Given an open poll with votes, When the owning teacher opens it, Then they see the votes per option and the number of participants; When another teacher or an org admin opens it, Then it is refused
- [UC-013] Given a closed poll with results after voting or after closing, When an enrolled student who did not vote opens it, Then they see the results
- [UC-013] Given a poll whose deadline has passed, When a student votes, Then it is refused
- [UC-013] Given a poll with votes, When the teacher changes max_choices or an option's label, or adds or removes an option, Then it is refused with 409 problem details; When they reorder the options or change results_visibility, Then it succeeds
- [UC-013] Given a poll, When the teacher opens the activity's submissions, Then there is no submissions list and the page links to the poll's results
- [UC-013] Given a student who voted, When their account is deleted, Then their participation is deleted and the tally is unchanged
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
- [OWN-005] Given a ROLE-Student user, When they read or modify an ENT-GroupMembership record the rule does not grant them (A student sees memberships only of groups they belong to or are invited to; only members change a group (invite, withdraw) and only its owner renames, transfers or dissolves it; a teacher sees groups only of course spaces they own, read-only; an org admin sees groups of any course space of their organisation, read-only), Then access is denied and nothing changes
- [OWN-005] Given a ROLE-Teacher user, When they read or modify an ENT-GroupMembership record the rule does not grant them (A student sees memberships only of groups they belong to or are invited to; only members change a group (invite, withdraw) and only its owner renames, transfers or dissolves it; a teacher sees groups only of course spaces they own, read-only; an org admin sees groups of any course space of their organisation, read-only), Then access is denied and nothing changes
- [OWN-005] Given a ROLE-OrgAdmin user, When they read or modify an ENT-GroupMembership record the rule does not grant them (A student sees memberships only of groups they belong to or are invited to; only members change a group (invite, withdraw) and only its owner renames, transfers or dissolves it; a teacher sees groups only of course spaces they own, read-only; an org admin sees groups of any course space of their organisation, read-only), Then access is denied and nothing changes
- [OWN-008] Given a ROLE-Student user, When they read or modify an ENT-TopicChoice record the rule does not grant them (A student changes only their own or their group's topic choice; a teacher sees choices only of their own activities), Then access is denied and nothing changes
- [OWN-008] Given a ROLE-Teacher user, When they read or modify an ENT-TopicChoice record the rule does not grant them (A student changes only their own or their group's topic choice; a teacher sees choices only of their own activities), Then access is denied and nothing changes
- [OWN-009] Given a ROLE-Student user, When they read or modify an ENT-TopicProposal record the rule does not grant them (A student reads only their own or their group's proposals; a teacher decides only proposals of their own activities), Then access is denied and nothing changes
- [OWN-009] Given a ROLE-Teacher user, When they read or modify an ENT-TopicProposal record the rule does not grant them (A student reads only their own or their group's proposals; a teacher decides only proposals of their own activities), Then access is denied and nothing changes
- [OWN-010] Given a ROLE-Student user, When they read or modify an ENT-Booking record the rule does not grant them (A student changes only their own or their group's booking; a teacher sees bookings only of their own activities), Then access is denied and nothing changes
- [OWN-010] Given a ROLE-Teacher user, When they read or modify an ENT-Booking record the rule does not grant them (A student changes only their own or their group's booking; a teacher sees bookings only of their own activities), Then access is denied and nothing changes
- [OWN-011] Given a ROLE-Student user, When they read or modify an ENT-PollParticipation record the rule does not grant them (A student sees only their own participation; the owning teacher sees vote counts per option and the number of participants at any time, never who voted or what anyone voted; other teachers and org admins do not see the poll (SCOPE-026)), Then access is denied and nothing changes
- [OWN-011] Given a ROLE-Teacher user, When they read or modify an ENT-PollParticipation record the rule does not grant them (A student sees only their own participation; the owning teacher sees vote counts per option and the number of participants at any time, never who voted or what anyone voted; other teachers and org admins do not see the poll (SCOPE-026)), Then access is denied and nothing changes
- [ENT-GroupMembership] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [ENT-TopicProposal] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [INT-001] Given EXT-002 is unavailable or times out, Then: Emails stay queued and are retried; no user flow is blocked; the in-app notification is created regardless; after the last retry an invitation shows 'email not delivered' to the admin, who can resend it

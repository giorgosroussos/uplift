<!-- GENERATED — do not edit -->
# Pack PHASE-2 — Organisations run with real users: invitations, profiles, settings, course spaces, enrolment, announcements and notifications

_design v3.5.1 · 1b77671cb19c_

Depends on: PHASE-1 · environment: development · components: CMP-Identity, CMP-Tenancy, CMP-Courses, CMP-Notifications, CMP-Audit

## Tasks

| task | title | depends on | writes |
|---|---|---|---|
| P2-T1 | Invitations and user management | — | CMP-Identity |
| P2-T2 | Profile, nickname, language and optional MFA for org admins | P2-T1 | CMP-Identity |
| P2-T3 | Organisation settings | P2-T1 | CMP-Tenancy, CMP-Identity |
| P2-T4 | Course spaces and enrolment | — | CMP-Courses |
| P2-T5 | Announcements | P2-T4 | CMP-Courses |
| P2-T6 | Notifications in-app and by email | P2-T1, P2-T5 | CMP-Notifications |
| P2-T7 | Audit entries for registration, users and settings | P2-T1, P2-T3, P2-T8 | CMP-Audit |
| P2-T8 | Archived course spaces hidden and emptied | P2-T4 | CMP-Courses |
| P2-T9 | Invitation delivery failure and resend | P2-T1 | CMP-Identity |

Execution waves: [P2-T1, P2-T4] → [P2-T2, P2-T5] → [P2-T3, P2-T6, P2-T8] → [P2-T7, P2-T9]

## Out of scope (do not build)

- Activities of any type (PHASE-3, PHASE-4)
- Groups (PHASE-4)
- Leaderboards and XP (PHASE-6)
- Dashboards beyond a placeholder home page per role (PHASE-7)
- Deadline reminders (PHASE-9)

## Use cases

#### UC-005 — Accept invitation (must)

Actors: ACT-Student, ACT-Teacher · Goal: Activate an invited account by setting a password

Main flow:
1. Invitee opens the link from the invitation email
2. Sees the organisation and assigned role, sets name and password
3. Account is activated with verified email and the invitee is logged in

Exceptions:
- Expired, revoked or already accepted invitation: rejected with a message to contact the admin

#### UC-032 — Manage users (must)

Actors: ACT-OrgAdmin · Goal: Invite users with a role, change roles, deactivate users

Main flow:
1. Admin opens the user list (search, filter by role and status, paginated)
2. Invites a user by email with a role; an invitation email is sent
3. Changes a user's role or deactivates/reactivates a user

Exceptions:
- Email already used by any user of the platform: invitation rejected (SEC-004: one organisation per user)
- Admin tries to deactivate or demote the last active org admin: rejected

#### UC-006 — Manage my profile (should)

Actors: ACT-Student, ACT-Teacher, ACT-OrgAdmin · Goal: Edit name, nickname, avatar, language and password; org admins also manage optional MFA

Main flow:
1. User opens their settings and edits name, nickname, avatar (from the preset set) and language (el, en or organisation default)
2. Changes the password by entering the current one; other sessions end
3. An org admin enables MFA by confirming the password and a first TOTP code, and may disable it

Exceptions:
- Nickname taken in the organisation (ignoring case) or invalid: not saved, the field shows the reason
- Wrong current password: password not changed
- Email, role, date of birth and minor flag are shown but not editable

#### UC-033 — Configure organisation (should)

Actors: ACT-OrgAdmin · Goal: Set logo, accent colour, terminology, grading scale, leaderboard on/off and default language

Main flow:
1. Org admin opens the organisation settings
2. Uploads or removes the logo, sets or clears the accent colour, edits the student and teacher terms in Greek and English, and chooses grading scale, leaderboard on/off and default language
3. Saves; the settings apply at once to every page of the organisation (DATA-011)

Exceptions:
- Accent colour not #RRGGBB, or logo not an allowed file (SEC-010): nothing is saved and the field shows the reason
- The subdomain is shown but cannot be changed

#### UC-021 — Manage course space (must)

Actors: ACT-Teacher · Goal: Create and edit a course space and manage its enrolled students

Main flow:
1. Teacher creates a course space with title, description and enrolment policy (open or enrolment key)
2. Edits it, archives it when the course ends after confirming that all students are unenrolled (DATA-012)
3. Views enrolled students, adds or removes students

Exceptions:
- Another teacher's space: the teacher cannot edit it

#### UC-011 — Browse catalogue and enrol (must)

Actors: ACT-Student · Goal: Find course spaces of the organisation and enrol

Main flow:
1. Student searches and filters the catalogue of active course spaces of their organisation
2. Opens a space and enrols: directly if the policy is open, by entering the enrolment key if the policy is key
3. The space appears among the student's enrolled spaces

Exceptions:
- Wrong enrolment key: not enrolled, error shown
- Already enrolled: no duplicate enrolment
- Archived space: enrolment refused

#### UC-008 — Post and read announcements (should)

Actors: ACT-Teacher, ACT-Student · Goal: Teacher posts announcements in a course space; students read them

Main flow:
1. Teacher opens their course space and posts an announcement with title and body; it is published at once
2. Enrolled students are notified (P2-T6) and read it in the course space, newest first
3. The teacher may edit or delete it later; an edit notifies nobody

Exceptions:
- Not the owning teacher: posting, editing and deleting are refused
- Archived space: the teacher may still post and edit, but nobody is notified and students cannot see the space (DATA-012)

#### UC-007 — View notifications (should)

Actors: ACT-Student, ACT-Teacher, ACT-OrgAdmin · Goal: See notifications grouped by day, mark read/unread, and turn notification emails off

Main flow:
1. User sees the unread count on the bell in the header and opens the notification centre
2. Notifications are listed newest first, grouped by day, 20 per page
3. Opening one marks it read and goes to its target; the user may mark entries unread or mark all as read
4. In their settings the user may turn DEC-001 notification emails off or on

## Entities

#### ENT-User — A person in exactly one organisation with exactly one role (SEC-004)

Owned by CMP-Identity · retention: While the account exists; erasure and anonymisation periods are set by SEC-003 · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| role | enum | string | required |
| name | string | string(255) | required, personal |
| nickname | string | string(255) | required, personal |
| avatar | string | string(255) |  |
| email | email | string(254) | required, unique, personal |
| password | string | string(255) | sensitive |
| date_of_birth | date | date | personal |
| is_minor | boolean | boolean | personal |
| email_verified_at | timestamp | timestamptz |  |
| is_active | boolean | boolean | required |
| locale | enum | string |  |
| two_factor_secret | text | text | sensitive |
| two_factor_recovery_codes | text | text | sensitive |
| two_factor_confirmed_at | timestamp | timestamptz |  |

Relations: one-to-many → ENT-Enrolment (on delete cascade); one-to-many → ENT-XpEvent (on delete cascade)

#### ENT-Invitation — Invitation of an email address to join an organisation with a role

Owned by CMP-Identity · retention: Deleted 30 days after it stops being pending · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| email | email | string(254) | required, personal |
| role | enum | string | required |
| token_hash | string | string(255) | required, sensitive |
| invited_by | uuid | uuid | → ENT-User, required |
| expires_at | timestamp | timestamptz | required |
| status | enum | string | required |
| email_failed_at | timestamp | timestamptz |  |

States: initial `pending`; allowed transitions: `pending -> accepted`, `pending -> revoked`, `pending -> expired`, `expired -> pending`

#### ENT-Organization — A tenant: school, university, tutoring centre or company

Owned by CMP-Tenancy · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| name | string | string(255) | required |
| subdomain | string | string(255) | required, unique |
| status | enum | string | required |
| logo_path | string | string(255) |  |
| accent_color | string | string(255) |  |
| student_term_el | string | string(255) | required |
| student_term_en | string | string(255) | required |
| teacher_term_el | string | string(255) | required |
| teacher_term_en | string | string(255) | required |
| grading_scale | enum | string | required |
| leaderboard_enabled | boolean | boolean | required |
| default_locale | enum | string | required |

Relations: one-to-many → ENT-User (on delete restrict); one-to-many → ENT-CourseSpace (on delete restrict)

States: initial `active`; allowed transitions: `active -> suspended`, `suspended -> active`

#### ENT-CourseSpace — A course space owned by exactly one teacher (SCOPE-007)

Owned by CMP-Courses · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| teacher_id | uuid | uuid | → ENT-User, required |
| title | string | string(255) | required |
| description | text | text |  |
| enrolment_policy | enum | string | required |
| enrolment_key | string | string(255) |  |
| status | enum | string | required |

Relations: one-to-many → ENT-Enrolment (on delete cascade); one-to-many → ENT-Activity (on delete restrict); one-to-many → ENT-Announcement (on delete cascade)

States: initial `active`; allowed transitions: `active -> archived`, `archived -> active`

#### ENT-Enrolment — A student's enrolment in a course space (unique per pair)

Owned by CMP-Courses · retention: Deleted when the course space is archived (DATA-012); follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| course_space_id | uuid | uuid | → ENT-CourseSpace, required |
| student_id | uuid | uuid | → ENT-User, required |
| enrolled_at | timestamp | timestamptz | required |

#### ENT-Announcement — Announcement posted in a course space

Owned by CMP-Courses · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| course_space_id | uuid | uuid | → ENT-CourseSpace, required |
| author_id | uuid | uuid | → ENT-User, required |
| title | string | string(255) | required |
| body | text | text | required |
| published_at | timestamp | timestamptz |  |

#### ENT-Notification — In-app notification of a user (Laravel database notifications)

Owned by CMP-Notifications · retention: Deleted 180 days after creation · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| user_id | uuid | uuid | → ENT-User, required |
| kind | string | string(255) | required |
| data | json | jsonb | required |
| read_at | timestamp | timestamptz |  |

#### ENT-NotificationPreference — A user's choice to receive DEC-001 notification emails (one per user)

Owned by CMP-Notifications · retention: While the account exists · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| user_id | uuid | uuid | → ENT-User, required, unique |
| email_enabled | boolean | boolean | required |

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

#### IdentityProviderResolver (INT-002: CMP-Identity ↔ EXT-001)

Mechanism: Laravel Socialite drivers per provider, configured per organisation (deferred)

Operations:
- `providersFor(organization_id) -> list of enabled providers`
- `resolveUser(organization_id, provider, external_subject) -> User or null`

Failure policy: timeout 10s, retries 0 (interactive login), idempotency key —; when unavailable: Login with that provider shows an error; users with a password can still log in with email and password

## External systems in `development`

| id | name | mode | tool | config vars |
|---|---|---|---|---|
| EXT-002 | Email delivery | catcher | Mailpit | MAIL_* |

## Constraints

- MUST NOT show individual grades, answers or submissions to ROLE-OrgAdmin  (SEC-007)
- MUST show ROLE-OrgAdmin only aggregated analytics  (SEC-007)
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
- MUST rate-limit organisation registration to 3 successful registrations per hour per IP; submissions rejected by validation do not count  (SEC-010)
- MUST rate-limit the live subdomain availability check to 60 requests per minute per IP  (SEC-010)
- MUST write an audit entry for organisation registration, grade changes, role changes, user deactivation and reactivation, organisation settings changes, course space archiving and platform admin actions  (SEC-010)
- MUST accept logo uploads only as png, jpg or sanitised svg up to 2 MB and CSV uploads up to 1 MB, stored outside the public directory  (SEC-010)
- MUST run composer audit and npm audit in CI  (SEC-010)
- MUST require TOTP MFA for ROLE-PlatformAdmin  (SEC-011)
- MUST offer optional TOTP MFA to ROLE-OrgAdmin  (SEC-011)
- MUST allow only the owning teacher to edit a course space and its activities  (SCOPE-007)
- MUST NOT let an organisation change its subdomain  (DATA-011)
- MUST accept accent_color only as #RRGGBB or empty  (DATA-011)
- MUST share {name, logo_url, accent_color, student_term, teacher_term (in the current locale), leaderboard_enabled, grading_scale} as the Inertia shared prop organization  (DATA-011)
- MUST serve the organisation logo through a tenant route from a private Storage disk  (DATA-011)
- MUST use the organisation's default_locale for guests and for users without a locale  (DATA-011)
- MUST dispatch App\Modules\Tenancy\Events\OrganizationSettingsChanged(organizationId, actorId, changes {field: {from, to}}) after commit; MUST NOT put file contents in changes  (DATA-011)
- MUST delete every enrolment of a course space in the same transaction that archives it  (DATA-012)
- MUST ask the teacher to confirm archiving, stating that all students are unenrolled  (DATA-012)
- MUST NOT show an archived course space, its activities or its announcements to students; requests for them return 404  (DATA-012)
- MUST dispatch App\Modules\Courses\Events\CourseSpaceArchived(organizationId, courseSpaceId, actorId, removedEnrolments) after commit  (DATA-012)
- MUST let only the owning teacher of the course space post, edit and delete its announcements  (DATA-013)
- MUST let only the owning teacher and students enrolled in the course space read its announcements  (DATA-013)
- MUST set published_at to the posting time; MUST NOT support drafts or scheduled announcements  (DATA-013)
- MUST dispatch App\Modules\Courses\Events\AnnouncementPublished(organizationId, courseSpaceId, announcementId, authorId) after commit when posting in an active course space, and on no other occasion  (DATA-013)
- MUST soft-delete announcements  (DATA-013)
- MUST NOT let a user change their own email, role, date_of_birth or is_minor  (DATA-014)
- MUST accept as avatar only one of the keys avatar-01 to avatar-12, or empty  (DATA-014)
- MUST NOT accept avatar uploads  (DATA-014)
- MUST accept nicknames of 3-30 characters made of letters, digits, '_', '-' and '.', unique within the organisation ignoring case  (DATA-014)
- MUST require the current password to change the password and end the user's other sessions  (DATA-014)
- MUST NOT offer self-service account deletion  (DATA-014)
- MUST set email_failed_at from the invitation notification's failed hook after the last retry, logging ids only  (DATA-015)
- MUST let only org admins of the invitation's organisation resend it, and only while it is pending or expired  (DATA-015)
- MUST issue a new token on resend, invalidate the old one, restart expires_at at 7 days and clear email_failed_at  (DATA-015)
- MUST NOT send email for notification kinds other than those listed in DEC-001  (DEC-001)
- MUST create the in-app notification even when the email fails  (DEC-002)
- MUST NOT put the announcement body or personal data in notification data or announcement emails  (DEC-002)
- MUST NOT send DEC-001 notification emails to a user who turned them off; MUST always send account emails  (DEC-002)
- MUST let a user read and change only their own notifications and notification preference  (DEC-002)
- MUST send notification emails in the user's locale, else the organisation's default_locale  (DEC-002)
- MUST let only org admins enable or disable MFA; other roles get 403 and the settings are hidden  (SEC-012)
- MUST require the current password and a valid first TOTP code to enable MFA  (SEC-012)
- MUST rate-limit the two-factor challenge to 5 attempts per minute per login session  (SEC-012)
- MUST clear two_factor_secret, two_factor_recovery_codes and two_factor_confirmed_at in the same transaction that changes a user's role away from org_admin  (SEC-012)
- MUST submit live answers through HTTP endpoints, never websocket client events (whisper)  (ARCH-006)
- MUST derive client countdowns from server-sent ends_at and server_now  (ARCH-006)
- MUST fall back to HTTP polling every 2 seconds when the websocket connection is lost  (ARCH-006)
- MUST log external calls with ids only, never emails or names  (ARCH-006)
- MUST authorise organisation settings in Tenancy's OrganizationPolicy through the OrganizationMember contract; MUST NOT reference Identity classes or role strings from Tenancy  (ARCH-008)
- MUST allow updating an organisation's settings only to a user who implements OrganizationMember, belongs to that organisation and administers it  (ARCH-008)
- MUST NOT write ENT-User fields name, nickname, email, password, date_of_birth, is_minor, two_factor_secret, two_factor_recovery_codes to logs or error messages
- MUST encrypt ENT-User fields password, two_factor_secret, two_factor_recovery_codes at rest
- MUST reject ENT-Invitation state transitions that are not listed in its state model
- MUST NOT write ENT-Invitation fields email, token_hash to logs or error messages
- MUST encrypt ENT-Invitation fields token_hash at rest
- MUST reject ENT-Organization state transitions that are not listed in its state model
- MUST reject ENT-CourseSpace state transitions that are not listed in its state model
- Emails are validated and compared case-insensitively.  (type email)
- Timestamps are stored in UTC.  (type timestamp)
- MUST enforce OWN-001 on every read and write of ENT-User: A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation
- MUST enforce OWN-002 on every read and write of ENT-Invitation: An org admin sees and revokes only invitations of their own organisation
- MUST enforce OWN-003 on every read and write of ENT-Enrolment: A student sees only their own enrolments; a teacher sees enrolments only of course spaces they own
- MUST enforce OWN-004 on every read and write of ENT-CourseSpace: A teacher edits only course spaces they own; an org admin may view but not edit course spaces of their organisation
- CMP-Identity MUST only depend on: CMP-Tenancy
- CMP-Tenancy MUST only depend on: no other component
- CMP-Courses MUST only depend on: CMP-Tenancy, CMP-Identity
- CMP-Notifications MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Courses, CMP-Activities, CMP-Assessment
- CMP-Audit MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Platform, CMP-Courses, CMP-Activities, CMP-Assessment
- MUST access EXT-002 only through Laravel Mail / Notification mail channel in CMP-Notifications
- MUST apply timeout 10s to every EXT-002 call; retries: 5, exponential backoff via queue (1m, 5m, 15m, 1h, 6h)
- MUST access EXT-001 only through IdentityProviderResolver in CMP-Identity
- MUST apply timeout 10s to every EXT-001 call; retries: 0 (interactive login)

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

- [UC-005] Given an invitation sent 8 days ago, When it is opened, Then it is rejected as expired
- [UC-005] Given an accepted invitation, When the link is opened again, Then it is rejected
- [UC-005] Given an invitation for role teacher, When accepted, Then the user has role teacher in the inviting organisation only
- [UC-032] Given an email already registered in another organisation, When an admin invites it, Then the invitation is rejected
- [UC-032] Given an organisation with exactly 1 active org admin, When that admin deactivates or demotes themselves, Then it is rejected
- [UC-032] Given a deactivated user with an open session, When they make their next request, Then they are logged out
- [UC-032] Given an admin of 'acme', When they request the user list, Then no user of another organisation appears
- [UC-006] Given a student of 'acme', When they set nickname 'Fox_7', avatar avatar-03 and language en, Then these are saved and leaderboards and broadcasts use them
- [UC-006] Given a user 'Fox_7' in 'acme', When another user of 'acme' sets nickname 'fox_7', Then it is rejected; a user of 'beta' may use it
- [UC-006] Given nicknames 'ab', a 31-character one, 'fox 7' and 'fox@7', When saved, Then each is rejected; 'Αλεπού.7' is accepted
- [UC-006] Given a user, When they submit a new email, role, date_of_birth or is_minor, Then none changes
- [UC-006] Given a user with two sessions, When they change their password with the correct current one, Then the other session is logged out; with a wrong current one nothing changes
- [UC-006] Given a teacher or student, When they try to enable MFA, Then it is refused with 403
- [UC-006] Given an org admin with MFA, When an admin demotes them to teacher, Then their MFA columns are empty and they log in without a code
- [UC-006] Given an org admin with MFA, When they enter 6 wrong codes within a minute, Then the 6th attempt is throttled
- [UC-033] Given an org admin of 'acme', When they set accent colour #1A2B3C and leaderboard off, Then acme's settings are saved and the shared prop organization shows them
- [UC-033] Given a teacher or student of 'acme', When they try to view or update acme's settings, Then the request is refused with 403
- [UC-033] Given an org admin of 'beta', When they try to update acme's settings, Then the request is refused and acme is unchanged
- [UC-033] Given an org admin, When they submit accent colour 'blue' or '#12345', Then nothing is saved; an empty accent colour is accepted
- [UC-033] Given acme has a logo, When the org admin removes it, Then the login page and app shell show the app logo
- [UC-033] Given acme's default language is el, When a guest or a user without a locale opens acme's login page, Then it is shown in Greek
- [UC-033] Given an org admin changes accent colour and leaderboard, When the save commits, Then one OrganizationSettingsChanged is dispatched with both fields as {from, to}
- [UC-033] Given an org admin submits a different subdomain, When saved, Then the subdomain is unchanged
- [UC-021] Given a teacher, When they create a course space, Then they are its owner and it is visible only within their organisation
- [UC-021] Given space S with 3 enrolled students, When its teacher archives it, Then S has no enrolments and one CourseSpaceArchived with removedEnrolments 3 is dispatched
- [UC-021] Given an archived space S, When a formerly enrolled student lists their spaces, browses the catalogue or opens S, Then S is absent and opening it returns 404
- [UC-021] Given an archived space S, When its teacher or an org admin of the organisation opens it, Then they can view it
- [UC-021] Given an archived space S, When its teacher restores it, Then S is active with no enrolments
- [UC-021] Given a space owned by teacher A, When teacher B tries to edit it or its activities, Then it is refused
- [UC-021] Given an org admin, When they open any course space of their organisation, Then they can view it
- [UC-011] Given a space with policy open, When a student enrols, Then they are enrolled without a key
- [UC-011] Given a space with policy key and key 'K7P2Q', When a student enters 'K7P2Q', Then they are enrolled; When they enter 'WRONG', Then they are not enrolled
- [UC-011] Given a student already enrolled, When they enrol again, Then exactly 1 enrolment exists
- [UC-011] Given a student of 'acme', When they browse the catalogue, Then no course space of another organisation is listed
- [UC-008] Given teacher A owns space S, When A posts 'Exam moved', Then it is shown to A and to every student enrolled in S, with published_at set, and AnnouncementPublished is dispatched once
- [UC-008] Given space S of teacher A, When teacher B, an org admin or a student tries to post, edit or delete in S, Then it is refused and nothing changes
- [UC-008] Given an announcement in S, When a student not enrolled in S, another teacher or an org admin requests S's announcements, Then none is shown
- [UC-008] Given A edits an announcement, Then title and body change, published_at is unchanged and no AnnouncementPublished is dispatched
- [UC-008] Given A deletes an announcement, Then it is soft-deleted and no longer shown
- [UC-008] Given S is archived, When A posts, Then it is stored and no AnnouncementPublished is dispatched
- [UC-007] Given teacher A posts in space S with enrolled students X and Y, Then X and Y each get one announcement_published entry and one queued email in their locale with the space name, title and link, and A gets none
- [UC-007] Given the mail transport fails for X, Then X still has the in-app entry
- [UC-007] Given X turned notification emails off, When A posts, Then X gets the in-app entry and no email; a password reset email is still sent to X
- [UC-007] Given X has 3 unread notifications, When X marks all as read, Then the bell shows 0
- [UC-007] Given user X, When they request or mark the notifications of user Y, Then it is refused and nothing changes
- [UC-007] Given an announcement is deleted after posting, When X opens its notification, Then the course space opens without it; if S is archived, Then it returns 404
- [OWN-001] Given a ROLE-Student user, When they read or modify an ENT-User record the rule does not grant them (A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation), Then access is denied and nothing changes
- [OWN-001] Given a ROLE-Teacher user, When they read or modify an ENT-User record the rule does not grant them (A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation), Then access is denied and nothing changes
- [OWN-001] Given a ROLE-OrgAdmin user, When they read or modify an ENT-User record the rule does not grant them (A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation), Then access is denied and nothing changes
- [OWN-002] Given a ROLE-OrgAdmin user, When they read or modify an ENT-Invitation record the rule does not grant them (An org admin sees and revokes only invitations of their own organisation), Then access is denied and nothing changes
- [OWN-003] Given a ROLE-Student user, When they read or modify an ENT-Enrolment record the rule does not grant them (A student sees only their own enrolments; a teacher sees enrolments only of course spaces they own), Then access is denied and nothing changes
- [OWN-003] Given a ROLE-Teacher user, When they read or modify an ENT-Enrolment record the rule does not grant them (A student sees only their own enrolments; a teacher sees enrolments only of course spaces they own), Then access is denied and nothing changes
- [OWN-004] Given a ROLE-Teacher user, When they read or modify an ENT-CourseSpace record the rule does not grant them (A teacher edits only course spaces they own; an org admin may view but not edit course spaces of their organisation), Then access is denied and nothing changes
- [OWN-004] Given a ROLE-OrgAdmin user, When they read or modify an ENT-CourseSpace record the rule does not grant them (A teacher edits only course spaces they own; an org admin may view but not edit course spaces of their organisation), Then access is denied and nothing changes
- [ENT-Invitation] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [ENT-Organization] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [ENT-CourseSpace] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [INT-001] Given EXT-002 is unavailable or times out, Then: Emails stay queued and are retried; no user flow is blocked; the in-app notification is created regardless; after the last retry an invitation shows 'email not delivered' to the admin, who can resend it
- [INT-002] Given EXT-001 is unavailable or times out, Then: Login with that provider shows an error; users with a password can still log in with email and password

<!-- GENERATED — do not edit -->
# Pack PHASE-9 — Ready for real users: GDPR and minors hardening, production hosting and operations, remaining could use cases

_design v3.5.0 · 174b6708ecea_

Depends on: PHASE-7, PHASE-8 · environment: development · components: CMP-Identity, CMP-Notifications, CMP-Tenancy

## Tasks

| task | title | depends on | writes |
|---|---|---|---|
| P9-T1 | GDPR export and erasure, minors' consent | — | CMP-Identity |
| P9-T2 | Deadline reminders | — | CMP-Notifications |
| P9-T3 | Find my organisation | — | CMP-Tenancy |
| P9-T4 | Production image, deploy script and backups | — | — |

Execution waves: [P9-T1, P9-T2, P9-T3] → [P9-T4]

## Out of scope (do not build)

- SSO (EXT-001, after the first production release)

## Use cases

#### UC-039 — Export or erase my data (could)

Actors: ACT-Student, ACT-Teacher, ACT-OrgAdmin · Goal: Exercise GDPR rights of access and erasure; becomes must when SEC-003 is activated

#### UC-038 — Deadline reminders (could)

Actors: ACT-Scheduler · Goal: Notify students before an activity deadline

#### UC-002 — Find my organisation (could)

Actors: ACT-Visitor · Goal: Enter a subdomain and be redirected to the organisation's space

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

## Constraints

- MUST make the deploy script refuse or wait while any live session has status running  (OPS-002)
- MUST NOT write ENT-User fields name, nickname, email, password, date_of_birth, is_minor, two_factor_secret, two_factor_recovery_codes to logs or error messages
- MUST encrypt ENT-User fields password, two_factor_secret, two_factor_recovery_codes at rest
- Emails are validated and compared case-insensitively.  (type email)
- Timestamps are stored in UTC.  (type timestamp)
- MUST enforce OWN-001 on every read and write of ENT-User: A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation
- CMP-Identity MUST only depend on: CMP-Tenancy
- CMP-Notifications MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Courses, CMP-Activities, CMP-Assessment
- CMP-Tenancy MUST only depend on: no other component
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

- [OWN-001] Given a ROLE-Student user, When they read or modify an ENT-User record the rule does not grant them (A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation), Then access is denied and nothing changes
- [OWN-001] Given a ROLE-Teacher user, When they read or modify an ENT-User record the rule does not grant them (A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation), Then access is denied and nothing changes
- [OWN-001] Given a ROLE-OrgAdmin user, When they read or modify an ENT-User record the rule does not grant them (A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation), Then access is denied and nothing changes
- [INT-001] Given EXT-002 is unavailable or times out, Then: Emails stay queued and are retried; no user flow is blocked; the in-app notification is created regardless; after the last retry an invitation shows 'email not delivered' to the admin, who can resend it
- [INT-002] Given EXT-001 is unavailable or times out, Then: Login with that provider shows an error; users with a password can still log in with email and password
- [phase] SEC-003, OPS-003, OPS-001 and the email provider of EXT-002 are decided by amendment, and staging/production environments are added, before any task of this phase starts

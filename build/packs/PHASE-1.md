<!-- GENERATED — do not edit -->
# Pack PHASE-1 — Walking skeleton: project, CI and all checks, modules, tenancy, organisation registration and login

_design v1.0.0 · cc071f14078f_

Depends on: none · environment: development · components: CMP-Tenancy, CMP-Identity, CMP-Audit

## Tasks

| task | title | depends on | writes |
|---|---|---|---|
| P1-T1 | Project skeleton, tooling and CI | — | — |
| P1-T2 | Tenancy core | P1-T1 | CMP-Tenancy |
| P1-T3 | Users and login per subdomain | P1-T2 | CMP-Identity |
| P1-T4 | Organisation registration | P1-T3 | CMP-Identity |
| P1-T5 | Audit log | P1-T1 | CMP-Audit |

Execution waves: [P1-T1] → [P1-T2, P1-T5] → [P1-T3] → [P1-T4]

## Out of scope (do not build)

- Invitations, user management, profile (PHASE-2)
- Course spaces and activities (PHASE-2, PHASE-3)
- Platform admin and MFA for admins (PHASE-2 and PHASE-8)
- SSO (EXT-001, future)
- Production image and deploy (PHASE-9)

## Use cases

#### UC-001 — Register organisation (must)

Actors: ACT-Visitor · Goal: Create an organisation with its subdomain and first admin account (self-serve)

Main flow:
1. Visitor opens sign-up on the apex domain
2. Enters organisation name and a subdomain; availability is checked live
3. Enters admin name, email and password
4. Organisation is created as active; the admin is created with ROLE-OrgAdmin and must verify the email
5. Visitor is redirected to the organisation subdomain

Exceptions:
- Subdomain taken, reserved or invalid: organisation is not created and the field shows the reason

#### UC-003 — Log in (must)

Actors: ACT-Student, ACT-Teacher, ACT-OrgAdmin · Goal: Sign in with email and password on the organisation subdomain

Main flow:
1. User opens the login page on their organisation's subdomain (organisation logo shown)
2. Enters email and password
3. Is redirected to the dashboard of their role

Exceptions:
- Wrong credentials: generic error, no hint whether the email exists
- User belongs to another organisation: login fails exactly like wrong credentials
- User or organisation deactivated: login refused
- Too many attempts: throttled

#### UC-004 — Recover password and verify email (must)

Actors: ACT-Student, ACT-Teacher, ACT-OrgAdmin · Goal: Reset a forgotten password; verify email address

Main flow:
1. User requests a reset link with their email on the organisation subdomain
2. Receives an email with a single-use link
3. Sets a new password

Exceptions:
- Unknown email: the same confirmation message is shown and no email is sent
- Expired or used link: rejected, user may request a new one

## Entities

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

Relations: one-to-many → ENT-Enrolment (on delete cascade); one-to-many → ENT-XpEvent (on delete cascade)

#### ENT-AuditEntry — Append-only record of security-relevant actions (SEC-010)

Owned by CMP-Audit · retention: 2 years, then deleted · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization |
| actor_type | enum | string | required |
| actor_id | uuid | uuid |  |
| action | string | string(255) | required |
| subject_type | string | string(255) | required |
| subject_id | uuid | uuid | required |
| changes | json | jsonb |  |
| occurred_at | timestamp | timestamptz | required |

## Interfaces

#### IdentityProviderResolver (INT-002: CMP-Identity ↔ EXT-001)

Mechanism: Laravel Socialite drivers per provider, configured per organisation (deferred)

Operations:
- `providersFor(organization_id) -> list of enabled providers`
- `resolveUser(organization_id, provider, external_subject) -> User or null`

Failure policy: timeout 10s, retries 0 (interactive login), idempotency key —; when unavailable: Login with that provider shows an error; users with a password can still log in with email and password

## External systems in `development`

| id | name | mode | tool | config vars |
|---|---|---|---|---|
| EXT-001 | Single sign-on identity providers | tbd |  |  |
| EXT-002 | Email delivery | catcher | Mailpit | MAIL_* |

## Constraints

- MUST require TOTP MFA for ROLE-PlatformAdmin  (SEC-011)
- MUST offer optional TOTP MFA to ROLE-OrgAdmin  (SEC-011)
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
- MUST run every operations.ci stage on each pull request and push to main  (OPS-004)
- MUST NOT merge a pull request whose CI fails  (OPS-004)
- MUST reject ENT-Organization state transitions that are not listed in its state model
- MUST NOT write ENT-User fields name, nickname, email, password, date_of_birth, is_minor to logs or error messages
- MUST encrypt ENT-User fields password at rest
- Emails are validated and compared case-insensitively.  (type email)
- Timestamps are stored in UTC.  (type timestamp)
- MUST enforce OWN-001 on every read and write of ENT-User: A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation
- CMP-Tenancy MUST only depend on: no other component
- CMP-Identity MUST only depend on: CMP-Tenancy
- CMP-Audit MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Platform, CMP-Courses, CMP-Activities, CMP-Assessment
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

- [UC-001] Given no organisation uses 'acme', When a visitor registers 'Acme' with subdomain 'acme', Then an active organisation with subdomain 'acme' exists and its only user has role org_admin
- [UC-001] Given an organisation with subdomain 'acme', When a visitor registers subdomain 'acme' or 'ACME', Then registration is rejected and no organisation or user is created
- [UC-001] Given the reserved subdomains www, app, api, admin, mail, When a visitor registers any of them, Then registration is rejected
- [UC-001] Given subdomains 'ab' (2 chars), '-acme', 'acme_1' and a 31-character name, When registered, Then each is rejected; 'acme-1' (3-30 chars, a-z, 0-9, inner hyphens) is accepted
- [UC-003] Given a teacher of 'acme', When they log in with correct credentials on acme's subdomain, Then they reach the teacher dashboard
- [UC-003] Given a teacher of 'acme', When they log in with correct credentials on the subdomain of 'beta', Then login fails with the same message as a wrong password
- [UC-003] Given a deactivated user, When they log in with correct credentials, Then login is refused
- [UC-003] Given 5 failed attempts for one email within 1 minute, When a 6th attempt is made in that minute, Then it is rejected without checking the password
- [UC-004] Given a reset link issued 61 minutes ago, When it is used, Then it is rejected
- [UC-004] Given a reset link already used once, When it is used again, Then it is rejected
- [UC-004] Given an email not registered in the organisation, When a reset is requested, Then the response is identical to a registered email and no email is sent
- [UC-004] Given a user with an unverified email, When they open any page other than verification, Then they are sent to the verification notice
- [OWN-001] Given a ROLE-Student user, When they read or modify an ENT-User record the rule does not grant them (A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation), Then access is denied and nothing changes
- [OWN-001] Given a ROLE-Teacher user, When they read or modify an ENT-User record the rule does not grant them (A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation), Then access is denied and nothing changes
- [OWN-001] Given a ROLE-OrgAdmin user, When they read or modify an ENT-User record the rule does not grant them (A student or teacher reads and edits only their own user record; an org admin manages only users of their own organisation), Then access is denied and nothing changes
- [ENT-Organization] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged
- [INT-002] Given EXT-001 is unavailable or times out, Then: Login with that provider shows an error; users with a password can still log in with email and password
- [phase] GitHub Actions runs every operations.ci stage on each pull request and push to main
- [phase] GET /up returns 200
- [phase] deptrac fails when a module depends on a module outside its may_depend_on
- [phase] A tenant-isolation test fails if any tenant-owned model lacks the organization_id global scope
- [phase] Every UI string resolves from lang/el and lang/en

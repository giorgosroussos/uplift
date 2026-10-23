<!-- GENERATED — do not edit -->
# Pack PHASE-8 — Platform administration: separate admin guard with required MFA, organisation management, platform dashboard

_design v3.3.0 · 7b74b682e4c4_

Depends on: PHASE-2 · environment: development · components: CMP-Platform, CMP-Audit

## Tasks

| task | title | depends on | writes |
|---|---|---|---|
| P8-T1 | Platform admin guard and MFA | — | CMP-Platform |
| P8-T2 | Manage organisations and platform dashboard | P8-T1 | CMP-Platform |
| P8-T3 | Audit of platform admin actions | P8-T2 | CMP-Audit |

Execution waves: [P8-T1] → [P8-T2] → [P8-T3]

## Out of scope (do not build)

- Impersonation of organisation users
- Billing (non-goal)

## Use cases

#### UC-036 — Manage organisations (should)

Actors: ACT-SuperAdmin · Goal: List organisations, see status, suspend or reactivate

#### UC-035 — Platform dashboard (could)

Actors: ACT-SuperAdmin · Goal: See all organisations and key platform metrics

## Entities

#### ENT-PlatformAdmin — Platform super-admin; separate from organisation users and outside tenancy (SEC-006)

Owned by CMP-Platform · retention: While the admin account exists · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| name | string | string(255) | required, personal |
| email | email | string(254) | required, unique, personal |
| password | string | string(255) | required, sensitive |

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

## Constraints

- MUST authenticate platform admins with a separate guard on the apex domain  (SEC-006)
- MUST NOT store platform admins in the organisation users table  (SEC-006)
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
- MUST rate-limit organisation registration to 3 successful registrations per hour per IP; submissions rejected by validation do not count  (SEC-010)
- MUST rate-limit the live subdomain availability check to 60 requests per minute per IP  (SEC-010)
- MUST write an audit entry for organisation registration, grade changes, role changes, user deactivation and reactivation, organisation settings changes, course space archiving and platform admin actions  (SEC-010)
- MUST accept logo uploads only as png, jpg or sanitised svg up to 2 MB and CSV uploads up to 1 MB, stored outside the public directory  (SEC-010)
- MUST run composer audit and npm audit in CI  (SEC-010)
- MUST NOT write ENT-PlatformAdmin fields name, email, password to logs or error messages
- MUST encrypt ENT-PlatformAdmin fields password at rest
- MUST reject ENT-Organization state transitions that are not listed in its state model
- Emails are validated and compared case-insensitively.  (type email)
- CMP-Platform MUST only depend on: CMP-Tenancy, CMP-Identity
- CMP-Audit MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Platform, CMP-Courses, CMP-Activities, CMP-Assessment

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

- [ENT-Organization] Given any state, When a transition not in the state model is attempted, Then it is rejected and the state is unchanged

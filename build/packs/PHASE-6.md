<!-- GENERATED — do not edit -->
# Pack PHASE-6 — Gamification: XP ledger, levels, badges, per-course-space leaderboards

_design v2.3.0 · 0e600f65152f_

Depends on: PHASE-5 · environment: development · components: CMP-Gamification

## Tasks

| task | title | depends on | writes |
|---|---|---|---|
| P6-T1 | XP ledger and levels | — | CMP-Gamification |
| P6-T2 | Badges | P6-T1 | CMP-Gamification |
| P6-T3 | Gamification profile and leaderboards | P6-T2 | CMP-Gamification |

Execution waves: [P6-T1] → [P6-T2] → [P6-T3]

## Out of scope (do not build)

- Dashboards showing XP (PHASE-7)

## Use cases

#### UC-037 — Award XP (should)

Actors: ACT-Scheduler · Goal: Award points, levels and badges from completed activities and live quizzes

#### UC-018 — View gamification profile (should)

Actors: ACT-Student · Goal: See XP, level progress, badges and leaderboard positions

## Entities

#### ENT-XpEvent — Ledger of XP awarded; level and totals are derived from it

Owned by CMP-Gamification · retention: Follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| user_id | uuid | uuid | → ENT-User, required |
| source_type | enum | string | required |
| source_id | uuid | uuid | required |
| xp | integer | integer | required |
| awarded_at | timestamp | timestamptz | required |

#### ENT-Badge — Badge definition

Owned by CMP-Gamification · retention: — · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| code | string | string(255) | required, unique |
| name | string | string(255) | required |
| description | text | text |  |

#### ENT-UserBadge — A badge earned by a user

Owned by CMP-Gamification · retention: Follows the student's erasure (SEC-003) · audit: no

| attribute | type | storage | notes |
|---|---|---|---|
| organization_id | uuid | uuid | → ENT-Organization, required |
| user_id | uuid | uuid | → ENT-User, required |
| badge_id | uuid | uuid | → ENT-Badge, required |
| earned_at | timestamp | timestamptz | required |

## Constraints

- MUST show only nickname and avatar on leaderboards  (SEC-008)
- MUST scope leaderboards to one course space  (SEC-008)
- Timestamps are stored in UTC.  (type timestamp)
- MUST enforce OWN-014 on every read and write of ENT-XpEvent: A student sees only their own XP events; others see only totals on leaderboards when enabled
- MUST enforce OWN-015 on every read and write of ENT-UserBadge: A student sees only their own badges
- CMP-Gamification MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Courses, CMP-Activities, CMP-Assessment, CMP-Live

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

- [OWN-014] Given a ROLE-Student user, When they read or modify an ENT-XpEvent record the rule does not grant them (A student sees only their own XP events; others see only totals on leaderboards when enabled), Then access is denied and nothing changes
- [OWN-015] Given a ROLE-Student user, When they read or modify an ENT-UserBadge record the rule does not grant them (A student sees only their own badges), Then access is denied and nothing changes
- [phase] SCOPE-018 is decided by amendment (badges, XP rules, level thresholds) before any task of this phase starts

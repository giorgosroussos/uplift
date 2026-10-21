<!-- GENERATED — do not edit -->
# Pack PHASE-7 — Analytics and dashboards for students, teachers and organisation admins

_design v2.2.0 · 4556fdd66232_

Depends on: PHASE-6 · environment: development · components: CMP-Analytics

## Tasks

| task | title | depends on | writes |
|---|---|---|---|
| P7-T1 | Teacher analytics and CSV export | — | CMP-Analytics |
| P7-T2 | Student and teacher dashboards | P7-T1 | CMP-Analytics |
| P7-T3 | Organisation dashboard and aggregate analytics | P7-T1 | CMP-Analytics |

Execution waves: [P7-T1] → [P7-T2] → [P7-T3]

## Out of scope (do not build)

- Platform dashboard (PHASE-8)

## Use cases

#### UC-029 — View analytics and export CSV (must)

Actors: ACT-Teacher · Goal: Analyse results per activity, student, course space and over time; export CSV

Main flow:
1. Teacher opens analytics for one of their course spaces
2. Sees per activity: score distribution, average, pass rate and questions ordered by percentage of correct answers
3. Sees per student and over time across the space's activities
4. Filters by period and activity and exports the shown data as CSV

#### UC-010 — Student dashboard (should)

Actors: ACT-Student · Goal: See enrolled spaces, upcoming activities and deadlines, recent grades, gamification snapshot

#### UC-020 — Teacher dashboard (should)

Actors: ACT-Teacher · Goal: See own spaces, items to grade, recent submissions and class statistics

#### UC-031 — Organisation dashboard (should)

Actors: ACT-OrgAdmin · Goal: Overview of users, activity and usage

#### UC-034 — Organisation analytics (could)

Actors: ACT-OrgAdmin · Goal: See aggregate figures for the organisation

## Entities

_none_

## Constraints

- MUST NOT show individual grades, answers or submissions to ROLE-OrgAdmin  (SEC-007)
- MUST show ROLE-OrgAdmin only aggregated analytics  (SEC-007)
- MUST round converted grades and percentages half-up to 1 decimal, only at display or export  (DATA-005)
- MUST NOT expose the list of participants of a poll to teachers or admins  (SEC-009)
- CMP-Analytics MUST only depend on: CMP-Tenancy, CMP-Identity, CMP-Courses, CMP-Activities, CMP-Assessment, CMP-Live, CMP-Gamification

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

- [UC-029] Given question Q1 answered correctly by 8 of 10 and Q2 by 2 of 10, Then Q2 is listed as harder than Q1 with 20% correct
- [UC-029] Given a pass mark of 50% and scores 40%, 50%, 90%, Then the pass rate is 66.7%
- [UC-029] Given a CSV export, Then it contains exactly the rows matching the current filters
- [UC-029] Given a teacher, When they open analytics of another teacher's course space, Then it is refused

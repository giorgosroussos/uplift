<!-- GENERATED from design/ — do not edit. Rebuild with tools/design/build.py -->
# Design rules — Uplift (design v3.0.0 · 6e6da0d35751)

Multi-tenant SaaS educational platform where organisations (universities, schools, tutoring centres, companies) run course spaces with interactive activities: quizzes, polls, assignment-topic selection and proposal, and presentation/exam slot booking. Its flagship is a real-time live quiz with gamification, backed by analytics for teachers and organisations.

## How you work in this repository
1. You work on one task id at a time. Get your brief with `python tools/design/build.py brief <TASK-ID>` and read `build/briefs/<TASK-ID>.md`. It contains everything the task needs. Do not read design/ wholesale; open a single design item only when the brief references it and you need detail.
2. design/, build/ (except build/briefs/), tools/design/ and this file are the source of truth. Never edit them; edits are blocked.
3. If the spec is unclear, contradictory or missing something, STOP. Do not improvise. Set your task to `blocked` and write `progress/requests/CR-<TASK-ID>-<n>.yaml` (e.g. CR-P1-T1-1, so parallel tasks never collide) (id, raised_by: implementation-agent, task, problem, affects, proposal, status: open).
4. Record progress only in `progress/tasks/<TASK-ID>.yaml`: status (in_progress | blocked | ready_to_merge), commit, and handoff (max 5 lines: what now exists and where). No narrative logs; history lives in git.
5. A task is finished when every acceptance criterion in the brief has a passing test (including the negative ones), lint and architecture checks pass, and `python tools/design/validate.py` passes. Then set status `ready_to_merge` and stop.
6. Never merge, rebase, cherry-pick, pull or resolve conflicts. The engineer integrates.
7. Never deploy to production or use production credentials.
8. Reference the task id in every commit message, e.g. `P2-T3: add payment gateway mock`.

## Stack
- backend: PHP 8.3
- backend: Laravel 13
- frontend: Inertia.js 3
- frontend: Vue 3.5
- frontend: TypeScript 5
- frontend: Tailwind CSS 4
- database: PostgreSQL 18
- cache: Redis 8
- other: Laravel Reverb 1
- worker: Laravel queue worker and scheduler 13
- storage: Local filesystem (Laravel Storage) 13
- architecture style: modular-monolith

## Global constraints
- MUST NOT build: Subscriptions, billing or payments for organisations  (non-goal)
- MUST NOT build: Native mobile apps (responsive web only)  (non-goal)
- MUST NOT build: Hosting of learning material (files, video, lectures); a full LMS  (non-goal)
- MUST NOT build: Chat or messaging between users  (non-goal)
- MUST NOT build: Integration with external LMS (Moodle, LTI)  (non-goal)
- MUST NOT build: Proctoring or anti-cheating for quizzes  (non-goal)
- MUST NOT build: UI languages other than Greek and English (SCOPE-020)  (non-goal)
- MUST store and process all application data, backups and logs within the EU  (ORG-001)
- MUST respect component may_depend_on rules; deptrac fails the build otherwise  (ARCH-004)
- MUST add new activity types by implementing the ActivityType contract, not by editing the Activities module  (ARCH-004)
- MUST write frontend code in TypeScript  (ARCH-005)
- MUST keep code compatible with PHP 8.3  (ARCH-005)
- MUST use UUID primary keys  (DATA-007)
- MUST store timestamps in UTC  (DATA-007)
- MUST put organization_id on every tenant-owned table, including child tables  (DATA-007)
- MUST include organization_id in unique constraints of tenant-owned tables  (DATA-007)
- MUST use the parent's id as primary key of Class Table Inheritance subtype tables  (DATA-007)
- MUST NOT soft-delete answers, votes or XP events  (DATA-007)
- MUST NOT introduce a paid third-party service without a recorded decision  (SCOPE-002)
- MUST NOT hardcode UI strings; every UI string comes from a translation key with Greek and English values  (SCOPE-020)
- MUST give every user exactly one organization_id and one role  (SEC-004)
- MUST NOT let a user act in an organisation other than their own  (SEC-004)
- MUST NOT add runtime dependencies outside the approved list: laravel/framework@^13.0, inertiajs/inertia-laravel@^3.0, laravel/fortify@^1.0, laravel/reverb@^1.0, vue@^3.5, @inertiajs/vue3@^3.0, laravel-echo@^2.0, pusher-js@^8.0, laravel-vue-i18n@^2.0, chart.js@^4.0, vue-chartjs@^5.0, vue-draggable-plus@^0.6, laravel/tinker@^3.0, laravel/wayfinder@^0.1, reka-ui@^2.0, @lucide/vue@^1.0, @vueuse/core@^12.0, class-variance-authority@^0.7, clsx@^2.0, tailwind-merge@^3.0, tw-animate-css@^1.0, vue-sonner@^2.0, vue-input-otp@^0.3. New ones need a change request.
- MUST read every external endpoint and credential from configuration; never hardcode them
- MUST NOT perform live payments, send real emails or call live external systems outside production
- Shared hotspot files (touch only when your task lists them): `composer.json`, `package.json`, `bootstrap/providers.php`, `routes/channels.php`

## Conventions
- Modules live in app/Modules/<Name>/ with Actions, Models, Policies, Events, Listeners, Http; each registers its own routes, broadcast channels and listeners in its service provider
- Vue pages live in resources/js/Pages/<Module>/; shared UI components come from shadcn-vue
- Controllers only validate, authorise and call one Action; business logic lives in Actions/Services (ARCH-003)
- Cross-module side effects go through domain events and listeners, never direct calls (ARCH-003)
- UI strings live in lang/el and lang/en and reach Vue through laravel-vue-i18n (SCOPE-020)
- Tests: Pest unit and feature tests; Pest browser tests for core flows including a multi-player live quiz; every ownership and tenant-isolation rule has a dedicated test
- Static checks: Pint, Larastan level 6, deptrac rules generated from may_depend_on, ESLint, Prettier, Vitest
- Development runs on Laravel Sail (PostgreSQL, Redis, Mailpit, Reverb)
- The few JSON endpoints (wizard autosave, subdomain check) return errors as RFC 9457 problem details
- Logs are structured JSON on stdout and contain no personal data
- Migrations are timestamped and live in their module
- All runtime packages are installed in phase 1; during phase 1 dev-only packages (build, test, lint, types) may be added without a decision and are listed in the P1-T1 handoff; after phase 1 any new package, runtime or dev, needs a change request (ARCH-007)

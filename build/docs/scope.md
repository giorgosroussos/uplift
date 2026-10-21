<!-- GENERATED from design/ — do not edit. Rebuild with tools/design/build.py -->
# Scope

_Generated document · design v2.3.0 · source 0e600f65152f_


**Category:** sensitive-data

## Problem

Interactive educational processes (quizzes, polls, topic assignment, slot booking) are run ad hoc alongside teaching; the platform supports and complements traditional teaching, it does not replace it.

## Goals

- In a live quiz, an answer is recorded and visible to the host within 1 second with 50 players
- A teacher creates and publishes an activity in at most 5 minutes
- Data of one organisation is never visible to another organisation (covered by automated tests)
- GDPR-grade protection of minors' data (deferred hardening, SEC-003)

## Non-goals

- Subscriptions, billing or payments for organisations
- Native mobile apps (responsive web only)
- Hosting of learning material (files, video, lectures); a full LMS
- Chat or messaging between users
- Integration with external LMS (Moodle, LTI)
- Proctoring or anti-cheating for quizzes
- UI languages other than Greek and English (SCOPE-020)

## Scale

| dimension | expected |
|---|---|
| users | ~10 organisations at start |
| concurrent | 1-2 simultaneous live quizzes with ~50 students each |
| data_volume | small |

## Project constraints

- Personal project; designed and developed by one person
- No deadline
- Budget must stay low, ideally free

## Environments

- development

## Decisions

| id | title | status | decision |
|---|---|---|---|
| SCOPE-001 | Positioning relative to traditional teaching | decided | The platform complements traditional teaching; it is not a full LMS replacement |
| SCOPE-002 | Budget | decided | Running and tooling costs stay low, preferably zero; free tiers and self-hosted open-source are preferred over paid services |
| SCOPE-003 | Non-goals of the current version | decided | Billing, native apps, learning-material hosting, chat, external LMS integration, proctoring and UI languages beyond Greek and English (SCOPE-020) are out of scope for this version; they are future ideas to design once the core works |
| SCOPE-004 | Environments | decided | Only a development environment exists for now; staging and production are added by amendment when needed |
| SCOPE-005 | Goal of organisation onboarding time | decided | No time target for organisation onboarding |
| SCOPE-006 | Enrolment policy | decided | Each course space has an enrolment policy chosen by its teacher: open or enrolment key |
| SCOPE-007 | Course space ownership | decided | A course space has exactly one teacher; org admins can view all course spaces of their organisation |
| SCOPE-008 | Self-paced quiz rules | decided | Attempts per quiz set by the teacher; on timeout the answered questions are auto-submitted; negative marking is optional per quiz; result visibility is set by the teacher, default: score visible immediately after submission |
| SCOPE-009 | Partial credit | decided | Multiple-choice and matching questions give proportional partial credit (e.g. 2 of 3 correct pairs earn 2/3 of the points) |
| SCOPE-010 | Live quiz scoring | decided | Live quiz awards fixed points per correct answer; no speed bonus for now |
| SCOPE-011 | Topic and slot rules | decided | The teacher sets the capacity of each topic and slot; a student or group may change or cancel a topic choice or slot booking until the deadline; a student whose proposed topic is rejected may propose again |
| SCOPE-012 | Group submissions | decided | In group activities one member submits on behalf of the group; the submission is visible to all members and every member receives the same grade |
| SCOPE-013 | Poll rules | decided | Polls are anonymous; a vote cannot be changed; results are visible after voting or after closing, as set by the teacher |
| SCOPE-014 | Live quiz outcome | decided | A live quiz awards XP and leaderboard points only; it does not produce a grade in the student's results |
| SCOPE-015 | Late join to a live quiz | superseded | A student joining after the start enters at the current question with 0 points for earlier questions |
| SCOPE-016 | Quiz mode | decided | The teacher chooses at creation whether a quiz is self-paced or live; a self-paced quiz has attempts, a live quiz has live sessions |
| SCOPE-017 | Reusable groups | decided | Groups belong to a course space and are reused across its group activities; each activity sets its maximum group size |
| SCOPE-018 | Gamification rules | deferred (inferred) | → PHASE-6 |
| SCOPE-019 | Live quiz content and runs | decided | Live quizzes contain only auto-gradable questions (no free text); teachers never grade live answers; a live quiz runs exactly once |
| SCOPE-020 | UI languages | decided | The UI is available in Greek and English; all UI strings live in translation files from the first phase. User-created content (questions, announcements) is not translated |
| SCOPE-021 | Live quiz model | decided | A live quiz is taken by all enrolled students at the same time: it starts manually from a lobby or automatically at a scheduled time (teacher's choice); one common end time = start + duration; late joiners get only the remaining time; students navigate freely, change answers until they submit or time ends, and may submit early. During the quiz the teacher sees live progress; students see correctness only at the end; at the end a final ranking with podium is shown. There is no per-question pacing by the teacher, no live leaderboard during the quiz and no projector mode |
| SCOPE-022 | Live quiz finish, lobby and scoring details | decided | A live session finishes early when every joined student has submitted; a lobby closed without starting does not count as a run and the quiz can be started later; multiple-choice and matching questions in live quizzes get proportional partial credit (SCOPE-009), no negative marking |

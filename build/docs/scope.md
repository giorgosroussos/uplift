<!-- GENERATED from design/ — do not edit. Rebuild with tools/design/build.py -->
# Scope

_Generated document · design v3.1.0 · source 6c4796be3fe5_


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
| SCOPE-023 | Self-paced quiz settings, grading lock and answer grading states | decided | Time limit optional, 1-1440 minutes, stored in seconds; max_attempts 1-100. A new quiz defaults to 1 attempt, score visible immediately, answers visible after the deadline, no time limit, no negative marking and no pass mark. Settings that change grading lock from the first attempt, including one in progress. An answer starts pending; on submission choice, true/false and matching answers become auto_graded and free-text answers stay pending until the teacher grades them; the teacher may also regrade an auto_graded answer. after_deadline visibility on a quiz without a deadline means visible immediately after the student submits |
| SCOPE-024 | Self-paced quiz scoring and attempt lifecycle | decided | Multiple choice earns points x max(0, correct selected - wrong selected) / number of correct options; matching earns points x correct matches / number of pairs; single choice and true/false earn all or nothing. With negative marking, only an answered auto-graded question that earns 0 loses fraction x points; partly correct answers keep their credit, unanswered and free-text questions are never penalised, and an attempt total never goes below 0. Each answer's points are rounded half-up to 2 decimals when stored; earned_points is their sum and max_points the sum of question points. An attempt ends at the earlier of started_at + time_limit and the deadline; without either it stays open until the student submits or the teacher closes the activity; at its end it is auto-submitted with the answers saved so far. On submission an attempt without pending answers becomes graded (final); otherwise it stays submitted (provisional) until every free-text answer is graded; a teacher regrade of an answer recalculates the total. An in-progress attempt counts toward max_attempts, and starting a new attempt while one is in progress is refused |
| SCOPE-025 | Group membership, ownership and lifecycle | decided | Every member of a group is enrolled in its course space; a student may belong to several groups of the same space. An enrolled student creates a group and becomes its owner and first member (membership created directly as member, the one exception to the initial state invited). Any member invites students enrolled in the space who are neither members of nor invited to that group, and any member may withdraw a pending invitation (invited -> withdrawn). After declined, left or withdrawn the student may be invited again: a new invited row, old rows unchanged; at most one invited or member row per (organization_id, group_id, student_id). Each group has one owner (owner_id); the owner renames it, transfers ownership to another member, or dissolves it; the owner may leave only after transferring ownership. A group is soft-deleted (deleted_at) when its owner dissolves it or its last member leaves; its pending invitations become withdrawn, it disappears for everyone, and its attempts, topic choices, proposals and bookings stay as history; a student who was a member when a group attempt was submitted keeps seeing that attempt and its grade after leaving the group or its deletion (membership joined_at and left_at decide it). When an enrolment is deleted (removal by the teacher, archiving), in the same transaction the student's memberships in that space become left and their pending invitations withdrawn; an owner's ownership passes to the remaining member with the earliest joined_at, and a group left with no members is soft-deleted. There is no group-level size limit; each activity checks its group_size when a group acts. In a group activity the acting student chooses which of their groups acts; in one activity each student takes part through at most one group, so a group cannot take part while any current member already takes part through another group. The owning teacher and org admins see groups read-only. Courses dispatches GroupInvitationCreated(organizationId, groupMembershipId) after commit when a student is invited, and GroupMembershipChanged(organizationId, groupMembershipId, fromStatus, toStatus) after commit on every status change |

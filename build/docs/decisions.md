<!-- GENERATED from design/ — do not edit. Rebuild with tools/design/build.py -->
# Decision record

_Generated document · design v3.4.0 · source f327e6c4cf53_


## Scope

### SCOPE-001 — Positioning relative to traditional teaching · decided

> Engineer: “δεν αντικαθιστά την παροδοσιακή εκπαιδευτική διαδικασία αλλά την συμπληρώνει”

**Decision:** The platform complements traditional teaching; it is not a full LMS replacement

_since v0.1.0_

### SCOPE-002 — Budget · decided

> Engineer: “το budget πρέπει να μείνει χαμηλό έως και δωρεάν”

**Decision:** Running and tooling costs stay low, preferably zero; free tiers and self-hosted open-source are preferred over paid services

**Constraints for implementation:**
- `MUST NOT introduce a paid third-party service without a recorded decision`

_since v0.1.0_

### SCOPE-003 — Non-goals of the current version · decided

> Engineer: “Για το 4 όλα που επισημαίνεις είναι μελλοντικά πλάνα και όχι της παρούσης αλλά αποτελούν ιδέες για μελλοντική δουλεία όταν το σύστημα πάρει μορφή και οι κύριες λειτουργίες του έιναι πλήρως λειτορυγικές και υλοποιημένες μπορεί να σχεδιαστούν τέτοιοι στόχοι.”

**Decision:** Billing, native apps, learning-material hosting, chat, external LMS integration, proctoring and UI languages beyond Greek and English (SCOPE-020) are out of scope for this version; they are future ideas to design once the core works

_since v0.1.0_

### SCOPE-004 — Environments · decided

> Engineer: “για τα περιβάλλοντα έχουμε μόνο dev το παρόν σύστημα”

**Decision:** Only a development environment exists for now; staging and production are added by amendment when needed

_since v0.1.0_

### SCOPE-005 — Goal of organisation onboarding time · decided

> Engineer: “για το γ δεν με πειράζει να είναι και ανω το 10 λεπτών είναι διαδικαστική διαδικασία που γίνεται μία φορά και όχι κατ'επανάληψη”

**Decision:** No time target for organisation onboarding

_since v0.1.0_

### SCOPE-006 — Enrolment policy · decided

> Engineer: “Εγγραφή φοιτητή σε χώρο μαθήματος θα μπορεί να γίνει είτε ελεύθερα είτε με κωδικό εγγραφής, ο καθηγητής προσδιορίζει έναν από τους δύο τρόπους ανά χώρο μαθήματος”

**Decision:** Each course space has an enrolment policy chosen by its teacher: open or enrolment key

_affects UC-011, UC-021 · since v0.1.0_

### SCOPE-007 — Course space ownership · decided

> Engineer: “Συνδιδασκαλία 1-1 ένας χώρος ένας καθηγητής και ναι ο OrgAdmin μπορεί να βλέπει τους χώρους μαθημάτων”

**Decision:** A course space has exactly one teacher; org admins can view all course spaces of their organisation

**Constraints for implementation:**
- `MUST allow only the owning teacher to edit a course space and its activities`

_affects UC-021 · since v0.1.0_

### SCOPE-008 — Self-paced quiz rules · decided

> Engineer: “το α είναι όσες ορίσει ο καθηγητής, το β ναι υποβάλλεται αυτόματα ότι έχει απαντήθει, το γ θα πρέπει να υπάρχει και αρνητική βαθμολόγηση (επιλογή του καθηγητή), το δ όπως ορίσει ο καθηγητής με default να βλέπει την βαθμολογία του ο φοιτητής αμέσως μετα την υποβολή”

**Decision:** Attempts per quiz set by the teacher; on timeout the answered questions are auto-submitted; negative marking is optional per quiz; result visibility is set by the teacher, default: score visible immediately after submission

_affects UC-012, UC-022 · since v0.1.0_

### SCOPE-009 — Partial credit · decided

> Engineer: “1β”

**Decision:** Multiple-choice and matching questions give proportional partial credit (e.g. 2 of 3 correct pairs earn 2/3 of the points)

_affects UC-012, UC-025 · since v0.1.0_

### SCOPE-010 — Live quiz scoring · decided

> Engineer: “σταθεροί πόντοι ανα σωστή απάντηση (να το κρατήσουμε πιο απλό για τώρα)”

**Decision:** Live quiz awards fixed points per correct answer; no speed bonus for now

_affects UC-024, UC-025 · since v0.1.0_

### SCOPE-011 — Topic and slot rules · decided

> Engineer: “α) όπως ορίσει ο καθηγητής, β) ναι μπορεί εντός προθεσμίας να αλλάξει/ακυρώσει, γ) ναι”

**Decision:** The teacher sets the capacity of each topic and slot; a student or group may change or cancel a topic choice or slot booking until the deadline; a student whose proposed topic is rejected may propose again

**Constraints for implementation:**
- `MUST NOT allow more holders of a topic or slot than its capacity, also under concurrent requests`

_affects UC-014, UC-015, UC-016, UC-027 · since v0.1.0_

### SCOPE-012 — Group submissions · decided

> Engineer: “ένας υποβάλει για όλους η υποβολή φαίνεται σε όλα τα μέλη της ομάδας | 2 ίδιο βαθμό”

**Decision:** In group activities one member submits on behalf of the group; the submission is visible to all members and every member receives the same grade

_affects UC-019, UC-012, UC-014, UC-015, UC-016 · since v0.1.0_

### SCOPE-013 — Poll rules · decided

> Engineer: “ανωνυμή ψηφοφορία χωρίς αλλαγή της ψήφου του και βλέπει το αποτέλεσμα όπως έχει ορίσει ο καθηγητής (είτε μετά την ψήφο είτε μετά το κλείσιμο)”

**Decision:** Polls are anonymous; a vote cannot be changed; results are visible after voting or after closing, as set by the teacher

**Constraints for implementation:**
- `MUST NOT expose to teachers or admins which student cast which vote`

_affects UC-013 · since v0.1.0_

### SCOPE-014 — Live quiz outcome · decided

> Engineer: “1 μόνο XP”

**Decision:** A live quiz awards XP and leaderboard points only; it does not produce a grade in the student's results

_affects UC-024, UC-025, UC-017, UC-037 · since v0.1.0_

### SCOPE-015 — Late join to a live quiz · superseded

> Engineer: “3 α”

**Decision:** A student joining after the start enters at the current question with 0 points for earlier questions

_affects UC-025 · since v0.1.0_

### SCOPE-016 — Quiz mode · decided

> Engineer: “μια χαρά ας το κάνουμε ως επιλογή του καθηγητή ειναι πιο καθαρό και απλο”

**Decision:** The teacher chooses at creation whether a quiz is self-paced or live; a self-paced quiz has attempts, a live quiz has live sessions

**Constraints for implementation:**
- `MUST NOT allow self-paced attempts on a live quiz`
- `MUST NOT allow live sessions on a self-paced quiz`

_affects UC-012, UC-022, UC-024, UC-025 · since v0.1.0_

### SCOPE-017 — Reusable groups · decided

> Engineer: “οχι επαναχρησιμοποιούμενες είναι το σωστό”

**Decision:** Groups belong to a course space and are reused across its group activities; each activity sets its maximum group size

_affects UC-019 · since v0.1.0_

### SCOPE-018 — Gamification rules · deferred · source: inferred

**Deferred to:** PHASE-6 · trigger: Core activities and live quiz are implemented

**Requirements:**
- List of badges and their conditions
- XP per activity type and outcome
- Level thresholds

**Constraints for implementation:**
- `MUST record XP only as XpEvent ledger rows, never as a stored total`
- `MUST award XP from domain event listeners, not inside activity actions`

_affects UC-037, UC-018 · since v0.1.0_

### SCOPE-019 — Live quiz content and runs · decided

> Engineer: “1 α) συμφωνώ, β) αυτόματη βαθμολόγηση μόνο οταν είναι live, γ) δεν βαθμολογεί ο καθηγητής στα live quiz. 2)δεν ξανατρέχει το ίδιο quiz.”

**Decision:** Live quizzes contain only auto-gradable questions (no free text); teachers never grade live answers; a live quiz runs exactly once

**Constraints for implementation:**
- `MUST reject free-text questions in a live quiz`
- `MUST NOT start a second live session for the same quiz`

_affects UC-022, UC-024, UC-025 · since v0.1.0_

### SCOPE-020 — UI languages · decided

> Engineer: “3 ελληνικά και αγγλικά”

**Decision:** The UI is available in Greek and English; all UI strings live in translation files from the first phase. User-created content (questions, announcements) is not translated

**Constraints for implementation:**
- `MUST NOT hardcode UI strings; every UI string comes from a translation key with Greek and English values`

_since v0.1.0_

### SCOPE-021 — Live quiz model · decided

> Engineer: “1 γ, 2 ναι υπάρχει κοινός χρόνος για όλους αν κάποιος μπεί καθυστερημένα εχει όσο διαθεσιμο χρόνο για την ληξη από την στιγμή που εισέρχεται και ναι μπορούν να υποβάλλουν και νωρίτερα. 3 α, γ στο τέλος, ε. 4 α παραμένει, β παραμένει, γ παραμένει, δ παραμένει. Ναι ο φοιτητής έχει το δικαίωμα στον διαθέσιμο χρόνο να πλοηγείται στις ερωτήσεις και να διορθώνει τις απαντήσεις του αν θέλει.”

**Decision:** A live quiz is taken by all enrolled students at the same time: it starts manually from a lobby or automatically at a scheduled time (teacher's choice); one common end time = start + duration; late joiners get only the remaining time; students navigate freely, change answers until they submit or time ends, and may submit early. During the quiz the teacher sees live progress; students see correctness only at the end; at the end a final ranking with podium is shown. There is no per-question pacing by the teacher, no live leaderboard during the quiz and no projector mode

**Why:** The engineer's intended model; the design brief's host-driven (Kahoot-style) flow was a misreading

**Rejected:**
- Host-driven question-by-question flow with per-question timers, reveal and projector view: not what the engineer intends

**Constraints for implementation:**
- `MUST fix one common end time for a live session at its start and enforce it on the server`
- `MUST NOT reveal correctness of any live answer before the session finishes`
- `MUST finish and score a live session at its end time without teacher action`

_affects UC-024, UC-025, UC-022, ENT-LiveSession, ENT-LiveAnswer, ENT-Quiz · supersedes SCOPE-015 · since v0.1.0_

### SCOPE-022 — Live quiz finish, lobby and scoring details · decided

> Engineer: “1 ναι, 2 οκ, 3 αναλογική βαθμολογία, συμφωνώ με όλες τις προεπιλογές”

**Decision:** A live session finishes early when every joined student has submitted; a lobby closed without starting does not count as a run and the quiz can be started later; multiple-choice and matching questions in live quizzes get proportional partial credit (SCOPE-009), no negative marking

**Constraints for implementation:**
- `MUST allow at most one non-cancelled live session per quiz`
- `MUST NOT apply negative marking in live quizzes`

_affects UC-024, UC-025, ENT-LiveSession · since v0.1.0_

### SCOPE-023 — Self-paced quiz settings, grading lock and answer grading states · decided

> Engineer: “1 Α, 2 Α, 3 Α, 4 Β, 5 Γ (αν το quiz δεν έχει deadline ας εμφανίζουμε τα αποτελέσματα αμέσως μετά την υποβολή των απαντήσεων από τους μαθητές)”

**Decision:** Time limit optional, 1-1440 minutes, stored in seconds; max_attempts 1-100. A new quiz defaults to 1 attempt, score visible immediately, answers visible after the deadline, no time limit, no negative marking and no pass mark. Settings that change grading lock from the first attempt, including one in progress. An answer starts pending; on submission choice, true/false and matching answers become auto_graded and free-text answers stay pending until the teacher grades them; the teacher may also regrade an auto_graded answer. after_deadline visibility on a quiz without a deadline means visible immediately after the student submits

**Why:** Refines SCOPE-008 with the gaps found in CR-P3-T3-1

**Constraints for implementation:**
- `MUST accept a time limit only of 1-1440 minutes or none, and max_attempts only of 1-100`
- `MUST refuse changes to questions, options, pairs, points, correct answers or negative marking of a quiz once any attempt of it exists, including an in-progress one`
- `MUST create answers as pending and move them on submission to auto_graded (choice, true/false, matching) or leave them pending (free text)`
- `MUST treat after_deadline score and answer visibility on a quiz without a deadline as visible immediately after submission`

_affects UC-012, UC-022, UC-026, ENT-Quiz, ENT-Answer, P3-T3, P3-T4, P3-T5 · since v2.4.0_

### SCOPE-024 — Self-paced quiz scoring and attempt lifecycle · decided

> Engineer: “1Α 2ΑΑΑ 3Α 4ΑΑ 5Α 6ΑB”

**Decision:** Multiple choice earns points x max(0, correct selected - wrong selected) / number of correct options; matching earns points x correct matches / number of pairs; single choice and true/false earn all or nothing. With negative marking, only an answered auto-graded question that earns 0 loses fraction x points; partly correct answers keep their credit, unanswered and free-text questions are never penalised, and an attempt total never goes below 0. Each answer's points are rounded half-up to 2 decimals when stored; earned_points is their sum and max_points the sum of question points. An attempt ends at the earlier of started_at + time_limit and the deadline; without either it stays open until the student submits or the teacher closes the activity; at its end it is auto-submitted with the answers saved so far. On submission an attempt without pending answers becomes graded (final); otherwise it stays submitted (provisional) until every free-text answer is graded; a teacher regrade of an answer recalculates the total. An in-progress attempt counts toward max_attempts, and starting a new attempt while one is in progress is refused

**Why:** Refines SCOPE-008 and SCOPE-009 with the gaps found in CR-P3-T4-1

**Constraints for implementation:**
- `MUST score multiple choice as points x max(0, correct selected - wrong selected) / correct options, matching as points x correct matches / pairs, single choice and true/false as all or nothing`
- `MUST apply negative marking only to answered auto-graded questions that earn 0, never to unanswered or free-text questions`
- `MUST NOT let an attempt's earned_points go below 0`
- `MUST round each answer's points_awarded half-up to 2 decimals when stored and compute earned_points as their sum`
- `MUST set ends_at to the earlier of started_at + time_limit and the activity's deadline, or null when neither exists`
- `MUST auto-submit an in-progress attempt at ends_at and when its activity is closed, with the answers saved so far`
- `MUST move a submitted attempt to graded once it has no pending answers`
- `MUST count in-progress attempts toward max_attempts and refuse a new attempt while one is in progress`

_affects UC-012, UC-026, ENT-QuizAttempt, ENT-Answer, P3-T4, P3-T5 · since v2.5.0_

### SCOPE-025 — Group membership, ownership and lifecycle · decided

> Engineer: “1 Α, 2 Β, 3 Β. Για τις αποφάσεις στα υπόλοιπα σημεία στο 1 δεν συμφωνώ ένας φοιτητής μπορεί να ανήκει σε περίσσοτερες από μία ομάδα για έναν χώρο μαθήματος, 2 όλοι οι συμμετέχοντες μίας ομάδας πρέπει να είναι εγγεγραμμένοι στο χώρο μαθήματος αλλά μπορούν να είναι ήδη σε κάποια ομάδα, 3 κατα την πρόσκληση φοιτητών οι διαθέσιμοι φοιτητές είναι οι εγγεγραμμένοι φοιτητές του χώρου μαθήματος που έχει επιλεγεί. 4 μπορεί να νέα πρόσκληση μετά από declined/left/withdraw ναι. 5 σκέφτομαι κάτι σαν ownership ομάδας, ο δημιουργός είναι ο πρώτος owner, το ownership μπορεί να μεταβιβαστεί από τον owner σε κάποιο άλλο team member, ο owner της ομάδας μπορεί να αλλάξει όνομα ή να διαγράψει/διαλύσει την ομάδα, αν μια ομάδα δεν έχει μέλη (αν δηλαδή και το τελευταίο μέλος αποχωρήσει) διαγράφεται αυτόματα. 6 συμφωνώ, 7 οκ / 1 Α, 2 Α, 3 Α, 4 Α / Ναι ακόμα και αν μια ομάδα έχει διαγραφεί τα πρώην μέλη της βλέπουν τις βαθμολογιμένες ομαδικές προσπάθειες που είχαν ολοκληρώσει με την ομάδα.”

**Decision:** Every member of a group is enrolled in its course space; a student may belong to several groups of the same space. An enrolled student creates a group and becomes its owner and first member (membership created directly as member, the one exception to the initial state invited). Any member invites students enrolled in the space who are neither members of nor invited to that group, and any member may withdraw a pending invitation (invited -> withdrawn). After declined, left or withdrawn the student may be invited again: a new invited row, old rows unchanged; at most one invited or member row per (organization_id, group_id, student_id). Each group has one owner (owner_id); the owner renames it, transfers ownership to another member, or dissolves it; the owner may leave only after transferring ownership. A group is soft-deleted (deleted_at) when its owner dissolves it or its last member leaves; its pending invitations become withdrawn, it disappears for everyone, and its attempts, topic choices, proposals and bookings stay as history; a student who was a member when a group attempt was submitted keeps seeing that attempt and its grade after leaving the group or its deletion (membership joined_at and left_at decide it). When an enrolment is deleted (removal by the teacher, archiving), in the same transaction the student's memberships in that space become left and their pending invitations withdrawn; an owner's ownership passes to the remaining member with the earliest joined_at, and a group left with no members is soft-deleted. There is no group-level size limit; each activity checks its group_size when a group acts. In a group activity the acting student chooses which of their groups acts; in one activity each student takes part through at most one group, so a group cannot take part while any current member already takes part through another group. The owning teacher and org admins see groups read-only. Courses dispatches GroupInvitationCreated(organizationId, groupMembershipId) after commit when a student is invited, and GroupMembershipChanged(organizationId, groupMembershipId, fromStatus, toStatus) after commit on every status change

**Why:** Resolves CR-P4-T1-1: UC-019 had only a goal; these rules decide the data (owner, soft delete, withdrawn, re-invitation rows) and how P4-T3..P4-T7 identify the acting group

**Constraints for implementation:**
- `MUST allow only students enrolled in the group's course space to create, be invited to or accept membership of a group`
- `MUST allow a student to be a member of several groups in the same course space`
- `MUST create the creator's membership as member with joined_at and set them as owner_id`
- `MUST keep at most one invited or member row per (organization_id, group_id, student_id) with a partial unique index; re-invitation creates a new row`
- `MUST let only members invite and withdraw invitations, and only the owner rename, transfer ownership or dissolve`
- `MUST refuse the owner leaving while other members remain`
- `MUST soft-delete a group when its owner dissolves it or it has no members left, and set its pending invitations to withdrawn`
- `MUST NOT delete or alter attempts, topic choices, proposals or bookings of a deleted group`
- `MUST set joined_at when a membership becomes member and left_at when it becomes left`
- `MUST let a student read a group attempt and its grade if they were a member of the group when it was submitted, even after leaving or the group's deletion`
- `MUST, when an enrolment is deleted, set the student's memberships in that space to left and their pending invitations to withdrawn in the same transaction, passing ownership to the member with the earliest joined_at`
- `MUST let the acting student choose which of their groups acts in a group activity, and refuse a group whose current member already takes part in that activity through another group`
- `MUST check ENT-Activity.group_size only when a group acts in an activity, not at group level`
- `MUST give teachers and org admins read-only access to groups (OWN-005)`
- `MUST dispatch App\Modules\Courses\Events\GroupInvitationCreated(organizationId, groupMembershipId) and GroupMembershipChanged(organizationId, groupMembershipId, fromStatus, toStatus) after commit`

_affects UC-019, ENT-Group, ENT-GroupMembership, OWN-005, OWN-006, SCOPE-017, P4-T1, P4-T3, P4-T4, P4-T5, P4-T6, P4-T7 · since v3.1.0_

### SCOPE-026 — Poll limits, defaults, visibility and edit lock · decided

> Engineer: “1 Α, 2 Α. ok”

**Decision:** A poll has 2-20 options; each label is required, at most 255 characters and unique within the poll ignoring case; max_choices is 1-20 and a poll cannot be published with max_choices above its number of options. A new poll defaults to max_choices 1 and results_visibility after_vote. The owning teacher sees votes per option and the number of participants at any time, also while the poll is open; other teachers and org admins do not see the poll (OWN-004). A student sees the results after their own vote when results_visibility is after_vote, and every enrolled student, voter or not, sees them once the poll is closed. A poll is closed when its activity is closed or its deadline has passed (server time); voting is refused from then on. Once anyone has voted, changes to max_choices and to the set or labels of the options are refused (409 problem details); reordering options and changing results_visibility stay allowed. Polls have no submissions overview (UC-028); the activity page links to the results. Deleting a user deletes their participations; the anonymous votes stay in the tally

**Why:** Resolves CR-P4-T2-1 by adopting the choices implemented on branch P4-T2; refines SCOPE-013 and SEC-009

**Constraints for implementation:**
- `MUST require 2-20 options per poll with labels of 1-255 characters, unique within the poll ignoring case`
- `MUST keep max_choices within 1-20 and refuse publishing with max_choices above the number of options`
- `MUST default a new poll to max_choices 1 and results_visibility after_vote`
- `MUST show the owning teacher the votes per option and the number of participants at any time, and never the participants`
- `MUST NOT show a poll to other teachers or to org admins`
- `MUST show results to every enrolled student once the poll is closed`
- `MUST treat a poll as closed when its activity is closed or its deadline has passed, and refuse votes from then on`
- `MUST refuse, once a vote exists, changes to max_choices and to the set or labels of options with 409 problem details`
- `MUST delete a user's poll participations with the account and keep the anonymous votes`

_affects UC-013, UC-028, ENT-Poll, ENT-PollOption, ENT-PollParticipation, OWN-011, SCOPE-013, SEC-009, P4-T2 · since v3.2.0_

### SCOPE-027 — Activity deletion · superseded

> Engineer: “Add activity deletion: the owning teacher can delete an activity of any type while it is a draft or has no submissions. / 1 A, 2 A, 3 A, 4 A”

**Decision:** The owning teacher may delete an activity of any type while it is a draft or has no submissions. A submission is any participation record: a quiz attempt in any status (also in progress), a started live session, a poll participation, a topic choice, a topic proposal or a booking (also cancelled). Each activity type answers through ActivityType::hasSubmissions(Activity) (ARCH-004). Deletion asks for confirmation, soft-deletes the activity (DATA-007) and has no restore in the UI; the deleted activity is absent for everyone and opening it, also from an earlier notification, returns 404. Nobody is notified and existing notifications are kept. Activities dispatches ActivityDeleted(organizationId, activityId, actorId, type) after commit, audited as activity.deleted

**Why:** New requirement from the engineer; a deletion that never removes participation data keeps grades, votes and bookings intact

**Constraints for implementation:**
- `MUST allow deletion only by the owning teacher (OWN-004) and only for a draft or an activity for which hasSubmissions is false`
- `MUST count any attempt (also in progress), a started live session, poll participation, topic choice, topic proposal or booking (also cancelled) as a submission`
- `MUST decide hasSubmissions through the ActivityType contract, implemented by each type's module`
- `MUST soft-delete the activity and provide no restore in the UI`
- `MUST return 404 for a deleted activity, also when opened from an existing notification`
- `MUST NOT send notifications on deletion nor delete existing notifications`
- `MUST dispatch App\Modules\Activities\Events\ActivityDeleted(organizationId, activityId, actorId, type) after commit and audit it as activity.deleted`

_affects UC-040, ENT-Activity, OWN-004, P4-T8, P5-T2 · since v3.3.0_

### SCOPE-029 — Activity deletion · decided

> Engineer: “Add activity deletion: the owning teacher can delete an activity of any type while it is a draft or has no submissions. / 1 A, 2 A, 3 A, 4 A / 1 A, 2 A, ok”

**Decision:** The owning teacher may delete an activity of any type while it is a draft or has no submissions. A submission is any participation record: a quiz attempt in any status (also in progress), a started live session, a poll participation, a topic choice, a topic proposal or a current booking (a cancelled booking is deleted, as a cancelled topic choice, SCOPE-028). Each activity type answers through ActivityType::hasSubmissions(Activity) (ARCH-004). Deletion asks for confirmation, soft-deletes the activity (DATA-007) and has no restore in the UI; the deleted activity is absent for everyone and opening it, also from an earlier notification, returns 404. Nobody is notified and existing notifications are kept. Activities dispatches ActivityDeleted(organizationId, activityId, actorId, type) after commit, audited as activity.deleted

**Why:** New requirement from the engineer; a deletion that never removes participation data keeps grades, votes and bookings intact

**Constraints for implementation:**
- `MUST allow deletion only by the owning teacher (OWN-004) and only for a draft or an activity for which hasSubmissions is false`
- `MUST count any attempt (also in progress), a started live session, poll participation, topic choice, topic proposal or current booking as a submission`
- `MUST decide hasSubmissions through the ActivityType contract, implemented by each type's module`
- `MUST soft-delete the activity and provide no restore in the UI`
- `MUST return 404 for a deleted activity, also when opened from an existing notification`
- `MUST NOT send notifications on deletion nor delete existing notifications`
- `MUST dispatch App\Modules\Activities\Events\ActivityDeleted(organizationId, activityId, actorId, type) after commit and audit it as activity.deleted`

_affects UC-040, ENT-Activity, OWN-004, P4-T8, P5-T2 · supersedes SCOPE-027 · since v3.4.0_

### SCOPE-028 — Slot booking rules · decided

> Engineer: “1 A, 2 A, ok”

**Decision:** A slot activity has 1-100 slots; each has capacity 1-1000, ends_at after starts_at, an optional location of at most 255 characters, and may overlap other slots. A slot with bookings cannot be removed and its capacity cannot drop below its bookings. Publishing needs at least one slot. slot_duration (optional, 5 minutes to 24 hours) only pre-fills ends_at = starts_at + slot_duration for new slots in the wizard and is not enforced. Booking, changing and cancelling are open while the activity is published and before its deadline; a slot whose starts_at has passed cannot be booked, and a booking in such a slot can no longer be changed or cancelled. A student or group has at most one booking per activity, enforced by the booking action under a lock of the activity row; changing moves the existing row to the new slot (slot_id updated) and cancelling deletes the row, as for topic choices. Capacity counts the bookings of a slot. Students see each slot's remaining places and their own or their group's booking, never other holders; the owning teacher sees every slot's holders (OWN-010)

**Why:** Resolves CR-P4-T5-1; mirrors the topic rules of P4-T3 so topic choices and bookings behave alike

**Constraints for implementation:**
- `MUST allow 1-100 slots per activity, capacity 1-1000, ends_at after starts_at and location of at most 255 characters`
- `MUST refuse removing a slot with bookings or lowering its capacity below its bookings`
- `MUST refuse publishing a slot activity without slots`
- `MUST use slot_duration (5 minutes to 24 hours) only to pre-fill ends_at of new slots`
- `MUST refuse booking a slot whose starts_at has passed, and changing or cancelling a booking in such a slot`
- `MUST keep at most one booking per student or group and activity, under a lock of the activity row`
- `MUST move the booking row on a change and delete it on cancellation`
- `MUST NOT show students who holds other bookings`

_affects UC-016, ENT-SlotActivity, ENT-Slot, ENT-Booking, OWN-010, SCOPE-011, SCOPE-029, P4-T5 · since v3.4.0_

## External

### DEC-001 — Email notifications · decided

> Engineer: “ανακοινώσεις, νέα δραστηριότητα (για χώρο μαθήματος που είναι εγγεγραμμένος, νέα βαθμολόγηση, έγγριση/απόρριψη θέματος, πρόσκληση σε ομάδα (δεν μου έρχεται κατι άλλο προς το παρόν)”

**Decision:** Besides account emails (invitation, password reset, verification), email is sent for: new announcement, new activity in an enrolled course space, new or final grade, topic approved or rejected, group invitation. Everything else is in-app only

**Constraints for implementation:**
- `MUST NOT send email for notification kinds other than those listed in DEC-001`

_affects EXT-002, UC-007, UC-008, UC-022, UC-026, UC-027, UC-019 · since v0.1.0_

### DEC-002 — Notification centre, announcement notifications and email opt-out · decided · source: inferred

**Decision:** Every user has a notification centre page, newest first, grouped by day, 20 per page, with read/unread per entry and 'mark all as read'; users cannot delete notifications. A bell in the app header shows the unread count, refreshed on page load (no live broadcast). In PHASE-2 the only kind is announcement_published: one in-app entry per student enrolled when it is posted (author excluded), with course space and announcement titles as they were then; its link obeys the normal access rules. Its email carries the space name, the announcement title and a link, not the body. Emails go in the user's locale, else the organisation's default_locale. Each user has one on/off switch for DEC-001 notification emails, shown in their settings; account emails cannot be turned off and in-app entries are always created

**Why:** Closes the gaps of UC-007 and of the announcement notification (CR-P2-T6-1) and the notification preferences of UC-006 (CR-P2-T2-1)

**Constraints for implementation:**
- `MUST create the in-app notification even when the email fails`
- `MUST NOT put the announcement body or personal data in notification data or announcement emails`
- `MUST NOT send DEC-001 notification emails to a user who turned them off; MUST always send account emails`
- `MUST let a user read and change only their own notifications and notification preference`
- `MUST send notification emails in the user's locale, else the organisation's default_locale`

_affects UC-006, UC-007, P2-T6 · since v2.1.0_

### DEC-003 — Grade notification timing · decided

> Engineer: “θέλω τα email να στέλνονται ότνα πρέπει όχι όταν βαθμολογούνται. Π.χ. αν μια προσπάθεια μαθητή ολοκληρώνεται αλλά το quiz δείχνει τα scores με την λήξη του deadline τότε πρέπει μόλις περάσει το deadline να σταλθούν τα mails, αν τα scores φαίνονται άμεσα μετά την υποβολή τότε τα mails πρέπει να φεύγουν άμεσα / 1 A, 2A, 3 A, 4 A, 5 A”

**Decision:** A student is notified of a quiz grade (grade_finalised, in-app entry and DEC-001 email together) when the final score becomes visible to them, not when it is graded. An attempt's result is released once it is graded (final) and its quiz's score_visibility is immediate, or after_deadline with no deadline or a deadline that has passed. Assessment records the release in ENT-QuizAttempt.result_released_at and dispatches ResultReleased(organization_id, attempt_id) exactly once: in the action that makes the attempt final when it is visible then, otherwise from a scheduled command running every minute that releases every graded, unreleased attempt that has become visible (deadline passed, visibility or deadline changed by the teacher). Visibility hidden releases nothing and sends no grade notification. A release is never revoked. Provisional scores send nothing. When a regrade changes earned_points of a released attempt, Assessment dispatches ResultChanged(organization_id, attempt_id) and the student gets a grade_changed notification (in-app and email); regrades before the release send nothing beyond the later release. Notifications listen to ResultReleased and ResultChanged, not to GradeFinalised. There are no per-student deadlines

**Why:** A notification must not announce a grade the student cannot see yet (CR-P3-T7-1); one marker on the attempt keeps the release idempotent between the finalising action and the scheduled command

**Constraints for implementation:**
- `MUST notify a quiz grade only when the attempt is graded and its score is visible to the student, never earlier`
- `MUST create the grade in-app entry and queue its email together, at the release`
- `MUST set result_released_at and dispatch ResultReleased at most once per attempt`
- `MUST release, within one minute through a scheduled command, graded attempts whose score has become visible because the deadline passed or the teacher changed visibility or deadline`
- `MUST NOT release or notify attempts of a quiz with score_visibility hidden`
- `MUST NOT notify provisional scores`
- `MUST send a grade_changed notification when a regrade changes earned_points of a released attempt, and none for regrades before the release`

_affects UC-026, UC-017, ENT-QuizAttempt, INT-001, P3-T7 · since v3.0.0_

## Data

### DATA-001 — Activity type modelling and JSONB use · decided

> Engineer: “PostgreSQL (Class Table Inheritance, JSONB μόνο για άμορφα δεδομένα)”

**Decision:** Type hierarchies (e.g. activity types) use Class Table Inheritance; JSONB is used only for genuinely unstructured data

_since v0.1.0_

### DATA-002 — Grading scale · decided

> Engineer: “Βαθμολογική κλίμακα ας το κρατήσουμε λίγο ανοιχτό στην διάθεση του οργανισμού και να προσφέρουμε α,β,δ”

**Decision:** Each organisation chooses its grading scale: 0-10 with one decimal, 0-100, or points per question with a pass mark as a percentage

_affects UC-017, UC-026, UC-029, UC-033 · since v0.1.0_

### DATA-003 — Score storage independent of grading scale · decided · source: inferred

**Decision:** Scores are stored as raw points and maximum points with exact decimals; the organisation's grading scale is applied only when displaying or exporting

**Why:** Lets an organisation change its scale without rewriting stored grades (DATA-002)

**Constraints for implementation:**
- `MUST store scores as earned points and max points using exact decimal types`
- `MUST convert to the organisation's grading scale only at display or export time`

_since v0.1.0_

### DATA-004 — Server-side quiz timing · decided · source: inferred

**Decision:** Quiz time limits and deadlines are enforced by the server clock; the client timer is display only

**Constraints for implementation:**
- `MUST enforce quiz time limits and deadlines on the server`
- `MUST NOT trust client timestamps for timing decisions`

_since v0.1.0_

### DATA-005 — Rounding of displayed grades · decided · source: inferred

**Decision:** Conversion from points to the organisation's grading scale rounds half-up to 1 decimal (e.g. 7/8 points on 0-10 is 8.8); percentages likewise to 1 decimal

**Constraints for implementation:**
- `MUST round converted grades and percentages half-up to 1 decimal, only at display or export`

_since v0.1.0_

### DATA-006 — Anonymous vote storage · decided · source: inferred

**Decision:** The fact that a student voted is stored separately from the chosen options; chosen options carry no user reference

**Constraints for implementation:**
- `MUST NOT store a user reference on poll choices`
- `MUST record poll participation separately to prevent a second vote`

_affects UC-013 · since v0.1.0_

### DATA-007 — Data conventions · decided · source: default

**Decision:** UUID primary keys; UTC timestamps; soft delete for User, CourseSpace, Activity, Question, Announcement; organization_id on every tenant-owned table including child tables; Laravel naming; CTI subtype tables use the parent id as primary key; XP as a ledger; badges defined platform-wide

**Constraints for implementation:**
- `MUST use UUID primary keys`
- `MUST store timestamps in UTC`
- `MUST put organization_id on every tenant-owned table, including child tables`
- `MUST include organization_id in unique constraints of tenant-owned tables`
- `MUST use the parent's id as primary key of Class Table Inheritance subtype tables`
- `MUST NOT soft-delete answers, votes or XP events`

_since v0.1.0_

### DATA-008 — Question bank · decided

> Engineer: “για το α συμφωνώ για το β θα έλεγα προσωπική με αυτό τον τρόπο ο εκάστωτε καθηγητής "χτίζει" την δική του τράπεζα και την διαχειρίζεται”

**Decision:** Each teacher has a personal question bank; adding a bank question to a quiz copies it, so later bank edits never change existing quizzes

**Constraints for implementation:**
- `MUST copy a bank question with its options and pairs when it is added to a quiz`
- `MUST NOT show a teacher's bank to other teachers`

_affects UC-022, UC-023, UC-030 · since v0.1.0_

### DATA-009 — Live answer storage · decided · source: default

**Decision:** LiveAnswer.response is JSONB: a snapshot used only for the answer distribution and XP (exception to DATA-001); self-paced answers stay structured

_since v0.1.0_

### DATA-010 — Initial settings of a registered organisation · decided · source: inferred

**Decision:** Sign-up asks for none of the organisation settings. A new organisation gets student terms Μαθητής / Student, teacher terms Καθηγητής / Teacher, grading scale 0-10, leaderboard enabled, and as default locale the UI locale the visitor used for sign-up; all are changeable later (UC-033)

**Why:** Keeps self-serve sign-up short (CR-P1-T4-1); every setting has a sensible value and the org admin adjusts it in settings

**Constraints for implementation:**
- `MUST create a registered organisation with student_term_el 'Μαθητής', student_term_en 'Student', teacher_term_el 'Καθηγητής', teacher_term_en 'Teacher', grading_scale scale_0_10 and leaderboard_enabled true`
- `MUST set a registered organisation's default_locale to the UI locale (el or en) of the sign-up request`

_affects UC-001, UC-033 · since v1.3.0_

### DATA-011 — Organisation settings format, effect and change event · decided · source: inferred

**Decision:** The subdomain is not editable by the organisation. accent_color is #RRGGBB or empty (theme default); the logo may be removed (the app logo is shown instead) and is served through a tenant route from a private Storage disk. Settings are shared with every page as the Inertia prop organization; the accent colour is the app's primary colour, the logo appears in the app shell and the login page header, and default_locale is the locale for guests and for users without their own. Using the student/teacher terms in screens is up to later tasks. After each change Tenancy dispatches OrganizationSettingsChanged, which P2-T7 audits

**Why:** Closes the gaps of UC-033 found in CR-P2-T3-1; a fixed subdomain avoids host-only session loss and broken links in sent mails

**Constraints for implementation:**
- `MUST NOT let an organisation change its subdomain`
- `MUST accept accent_color only as #RRGGBB or empty`
- `MUST share {name, logo_url, accent_color, student_term, teacher_term (in the current locale), leaderboard_enabled, grading_scale} as the Inertia shared prop organization`
- `MUST serve the organisation logo through a tenant route from a private Storage disk`
- `MUST use the organisation's default_locale for guests and for users without a locale`
- `MUST dispatch App\Modules\Tenancy\Events\OrganizationSettingsChanged(organizationId, actorId, changes {field: {from, to}}) after commit; MUST NOT put file contents in changes`

_affects UC-033, P2-T3, P2-T7 · since v1.4.0_

### DATA-012 — Archived course spaces are hidden from students and emptied · decided

> Engineer: “Θα έλα οι archived course space να μην εμφανίζονται στους φοιτητές και να μπορεί να τους δεί μονο ο teacher. Επίσης ίσως αν ένα corse space μπαίνει archived να αδείαζει από εγγεγραμμένους φοιτητές”

**Decision:** Archiving a course space deletes all its enrolments, after a confirmation in the UI, and hides it from students: it is absent from their lists and the catalogue and opening it returns 404. Only the owning teacher (and org admins, OWN-004) see an archived space. Restoring it gives an active space with no students; they enrol again. Courses dispatches CourseSpaceArchived, which is audited

**Why:** Chosen by the engineer knowing it is irreversible: students lose access to the space's results and later per-class analytics lose its roster (CR-P2-T5-1)

**Rejected:**
- Hide but keep enrolments: reversible, keeps the roster
- Read-only access for enrolled students (previous UC-021 criterion)

**Constraints for implementation:**
- `MUST delete every enrolment of a course space in the same transaction that archives it`
- `MUST ask the teacher to confirm archiving, stating that all students are unenrolled`
- `MUST NOT show an archived course space, its activities or its announcements to students; requests for them return 404`
- `MUST dispatch App\Modules\Courses\Events\CourseSpaceArchived(organizationId, courseSpaceId, actorId, removedEnrolments) after commit`

_affects UC-008, UC-011, UC-021, P2-T7, P2-T8 · since v2.0.0_

### DATA-013 — Announcements · decided · source: inferred

**Decision:** Announcements are stored in an announcements table in Courses (composite FK to course_spaces, cascade; author_id restrict; soft delete per DATA-007). Posting publishes at once (no drafts or scheduling). The owning teacher posts, edits (title, body) and soft-deletes, also in an archived space; an edit keeps published_at and notifies nobody. The owning teacher and enrolled students read them; org admins, other teachers and non-enrolled students do not. Posting in an active space dispatches AnnouncementPublished for P2-T6; posting in an archived space dispatches nothing

**Why:** Closes the gaps of UC-008 found in CR-P2-T5-1

**Constraints for implementation:**
- `MUST let only the owning teacher of the course space post, edit and delete its announcements`
- `MUST let only the owning teacher and students enrolled in the course space read its announcements`
- `MUST set published_at to the posting time; MUST NOT support drafts or scheduled announcements`
- `MUST dispatch App\Modules\Courses\Events\AnnouncementPublished(organizationId, courseSpaceId, announcementId, authorId) after commit when posting in an active course space, and on no other occasion`
- `MUST soft-delete announcements`

_affects UC-008, P2-T5, P2-T6 · since v2.0.0_

### DATA-014 — Profile fields, avatars and nicknames · decided · source: inferred

**Decision:** A user edits their name, nickname, avatar and locale (el, en, or empty = organisation default). Email, role, date_of_birth and is_minor are not editable by the user. The avatar is chosen from 12 SVG avatars bundled with the app (keys avatar-01 to avatar-12); there are no uploads, and without a choice the UI shows the user's initials. Nicknames are 3-30 characters (letters of any script, digits, '_', '-', '.'), trimmed, unique within the organisation ignoring case. Password change requires the current password, applies SEC-010 and ends the user's other sessions. There is no self-service account deletion (UC-039)

**Why:** Closes the profile gaps of CR-P2-T2-1; preset avatars keep photos of minors off leaderboards and live broadcasts

**Rejected:**
- Uploaded avatar images: personal data of minors on leaderboards and broadcasts, plus a new upload rule

**Constraints for implementation:**
- `MUST NOT let a user change their own email, role, date_of_birth or is_minor`
- `MUST accept as avatar only one of the keys avatar-01 to avatar-12, or empty`
- `MUST NOT accept avatar uploads`
- `MUST accept nicknames of 3-30 characters made of letters, digits, '_', '-' and '.', unique within the organisation ignoring case`
- `MUST require the current password to change the password and end the user's other sessions`
- `MUST NOT offer self-service account deletion`

_affects UC-006, P2-T2 · since v2.1.0_

### DATA-015 — Invitation delivery failure and resend · decided · source: inferred

**Decision:** When the invitation email fails after its last retry (INT-001), Identity sets email_failed_at on the invitation and the Users page shows 'Email not delivered' with a Resend button. Any org admin of the organisation may resend a pending or expired invitation: a new token is issued (the old link stops working), expires_at restarts at 7 days, the invitation becomes pending, email_failed_at is cleared and the email is queued again

**Why:** The invitation email, its list and ENT-Invitation belong to Identity, which may not depend on Notifications (CR-P2-T6-1)

**Constraints for implementation:**
- `MUST set email_failed_at from the invitation notification's failed hook after the last retry, logging ids only`
- `MUST let only org admins of the invitation's organisation resend it, and only while it is pending or expired`
- `MUST issue a new token on resend, invalidate the old one, restart expires_at at 7 days and clear email_failed_at`

_affects UC-032, P2-T9 · since v2.1.0_

### DATA-016 — Question validation and bank editing · decided · source: inferred

**Decision:** Per type: single_choice 2-10 options with exactly 1 correct; multiple_choice 2-10 options with at least 1 correct; true_false exactly 2 options with the fixed texts 'true' and 'false' and exactly 1 correct, shown through translation keys in the viewer's locale; matching 2-10 pairs with no empty side; free_text no options or pairs. Points are greater than 0 and at most 100, with 2 decimal places. A teacher edits and hard-deletes their own bank questions; quiz copies are separate rows (DATA-008) and are unaffected. Drag-and-drop in the builder orders options and pairs (position); the bank list is ordered newest first

**Why:** Closes the validation gaps of UC-023 found in CR-P3-T2-1; true/false labels are UI strings (SCOPE-020), so they are not stored in one language

**Constraints for implementation:**
- `MUST refuse a single_choice or true_false question without exactly 1 correct option`
- `MUST refuse a multiple_choice question without at least 1 correct option`
- `MUST refuse choice questions with fewer than 2 or more than 10 options and matching questions with fewer than 2 or more than 10 pairs or an empty side`
- `MUST store true_false option texts as 'true' and 'false' and display them only through translation keys`
- `MUST refuse points outside (0, 100] or with more than 2 decimal places`
- `MUST NOT change or delete quiz copies when a bank question is edited or deleted`

_affects UC-023, ENT-Question, ENT-ChoiceOption, ENT-MatchingPair, P3-T2 · since v2.3.0_

### DATA-017 — CSV question import format and lifecycle · decided · source: inferred

> Engineer: “οκ”

**Decision:** UTF-8 CSV (optional BOM ignored) with a required header row of fixed English names: type, prompt, points, correct, option_1 ... option_10, in any order. type is the stored value (single_choice, multiple_choice, true_false, matching, free_text). Choice types: option_n hold the option texts in order (empty trailing cells ignored) and correct lists the 1-based numbers of the correct options separated by '|'. true_false: no options, correct is 'true' or 'false'. matching: each option_n holds one pair 'left => right' (split on the first ' => '), correct empty. free_text: no options, correct empty. Delimiter ',' or ';' detected from the header row; points accept '.' or ',' as decimal separator. Every row is validated with DATA-016. The report lists every row with its line number and 'valid' or its translated errors. Confirm imports all valid rows into the uploader's bank (in_bank = true, owner = uploader) in one transaction in file order and skips invalid rows; it is disabled when no row is valid; the result shows imported and skipped counts. At most 500 data rows per file. The upload is stored on the private local disk under question-imports/<organization_id>/<user_id>/<uuid>.csv, re-parsed and re-validated at confirm, and deleted after confirm or cancel; a daily scheduled command deletes leftovers older than 24 hours. The import page offers a template CSV with the header and one example row per type

**Why:** Closes the format, semantics and lifecycle gaps of UC-030 found in CR-P3-T6-1; fixed English headers keep one template for both UI languages; re-validation at confirm keeps no parsed state between requests

**Constraints for implementation:**
- `MUST refuse the whole file when it is not valid UTF-8, with a message to save it as CSV UTF-8`
- `MUST refuse the whole file when a required header is missing or an unknown header is present`
- `MUST refuse the whole file when it has more than 500 data rows or exceeds 1 MB`
- `MUST validate every row with the DATA-016 rules and report each invalid row's line number and translated errors`
- `MUST import only valid rows, in file order, in one transaction, into the uploader's own bank`
- `MUST re-parse and re-validate the stored file at confirm`
- `MUST let only the uploader confirm or cancel an import`
- `MUST store uploads on the private disk under question-imports/<organization_id>/<user_id>/ and delete them after confirm or cancel`
- `MUST delete leftover import files older than 24 hours with a daily scheduled command`

_affects UC-030, ENT-Question, ENT-ChoiceOption, ENT-MatchingPair, P3-T6 · since v2.6.0_

## Security

### SEC-001 — Authentication · superseded

> Engineer: “Auth: Laravel Breeze (preset Inertia + Vue), με προετοιμασμένο σημείο για SSO αργότερα μέσω Socialite | MFA: 1 συμφωνώ”

**Decision:** Email and password authentication via Laravel Breeze (Inertia + Vue preset); SSO via Socialite is deferred but the integration point is prepared. TOTP MFA is required for platform admins, optional for org admins, not offered to teachers and students

**Constraints for implementation:**
- `MUST require TOTP MFA for ROLE-PlatformAdmin`
- `MUST offer optional TOTP MFA to ROLE-OrgAdmin`

_since v0.1.0_

### SEC-002 — Authorisation model · decided

> Engineer: “Ρόλοι: enum + Laravel Policies”

**Decision:** User role is an enum; authorisation is enforced through Laravel Policies

_since v0.1.0_

### SEC-003 — GDPR and minors' data hardening · deferred

> Engineer: “ο στόχος είναι οι χρήστες να είναι σχολεία, πανεπιστήμια, φροντιστήρια με ανήλικους χρήστες, μπορούμε ίσως όμως αυτό το hardening σε επίπεδο sensitive data και GDPR να το κάνουμε αργότερα, θέλω να το κρατήσουμε καταγεγραμμένο ώς στόχο”

**Deferred to:** PHASE-9 · trigger: Core features work and the system is about to host real users

**Requirements:**
- Lawful basis and parental consent handling for minors
- Data export and erasure per user
- Retention periods per entity
- Audit log of access to personal data

**Constraints for implementation:**
- `MUST mark every attribute holding personal data with sensitivity: personal in the model`
- `MUST NOT write personal data (emails, names, answers) to logs`
- `MUST reference users only by user_id in results, grades and analytics so users can be anonymised without breaking them`
- `MUST store a date of birth or minor flag per user`

_since v0.1.0_

### SEC-004 — User, role and organisation cardinality · decided

> Engineer: “ένας χρήστης ένας ρόλος και 1-1 και στους χρήστες των οργανισμών δηλαδή κάθε χρήστης αντιστοιχεί σε έναν οργανισμός και όχι σε περισσότερους”

**Decision:** Each user belongs to exactly one organisation and has exactly one role

**Constraints for implementation:**
- `MUST give every user exactly one organization_id and one role`
- `MUST NOT let a user act in an organisation other than their own`

_since v0.1.0_

### SEC-005 — Live quiz participation · decided

> Engineer: “νάι μόνο συνδεδεμένοι φοιτητές που είναι εγγεγραμμένοι στο χώρο μαθήματος”

**Decision:** Only authenticated students enrolled in the course space can join its live quizzes; no anonymous PIN join

**Constraints for implementation:**
- `MUST reject a live quiz join from a user who is not enrolled in the quiz's course space`
- `MUST NOT offer anonymous or PIN-only participation`

_affects UC-025 · since v0.1.0_

### SEC-006 — Platform super-admins outside tenancy · decided

> Engineer: “το 1 ειναι σωστό έχεις δίκιο”

**Decision:** Super-admins live in a separate platform_admins table with their own guard, log in on the apex domain and are never members of an organisation

**Constraints for implementation:**
- `MUST authenticate platform admins with a separate guard on the apex domain`
- `MUST NOT store platform admins in the organisation users table`

_affects ACT-SuperAdmin, UC-035, UC-036 · since v0.1.0_

### SEC-007 — Organisation admin oversight · decided

> Engineer: “οχι ο OrgAdmin βλέπει τους οργανισμούς, analytics, διαχειρίζεται τους οργανισμούς και γενικότερα έχει high level εποπτεία του συστήματος | με συγνωρείς έκανα λάθος ο OrgAdmin ναι πρέπει να βλέπει χώρους μαθημάτων και καθηγητές και φοιτητές”

**Decision:** Org admins have high-level oversight of their own organisation: they see its course spaces, teachers and students, manage users and settings, and see aggregate analytics; they never see individual grades, answers or submissions

**Constraints for implementation:**
- `MUST NOT show individual grades, answers or submissions to ROLE-OrgAdmin`
- `MUST show ROLE-OrgAdmin only aggregated analytics`

_affects ROLE-OrgAdmin, UC-031, UC-034, UC-021 · since v0.1.0_

### SEC-008 — Leaderboard identity · decided

> Engineer: “3 γ ανα χώρο μαθήματος | οκ”

**Decision:** Leaderboards are per course space and show only a nickname and avatar chosen by the student, never real names. A nickname is auto-generated until the student changes it, is unique within the organisation, and teachers see it next to the real name

**Constraints for implementation:**
- `MUST show only nickname and avatar on leaderboards`
- `MUST scope leaderboards to one course space`

_affects UC-018, UC-024, UC-025, ENT-User · since v0.1.0_

### SEC-009 — Poll anonymity for teachers · decided

> Engineer: “οχι θέλω ανωνυμότητα στην ψηφοφορία, θέλω να βλέπει ψήφους χωρίς να ξέρει ποιος ψήφισε και τι ψήφισε”

**Decision:** Teachers see vote counts per option only; never who voted or what any student voted

**Constraints for implementation:**
- `MUST NOT expose the list of participants of a poll to teachers or admins`

_affects UC-013, ENT-PollParticipation · since v0.1.0_

### SEC-010 — Security baseline · decided · source: default

**Decision:** argon2id passwords (min 8, uncompromised check); host-only session cookie per subdomain with CSRF; private/presence Reverb channels authorised by the same Policies; rate limits; audit log; restricted uploads; security headers; dependency audits; OWASP Top 10 baseline; GDPR in scope, no PCI

**Constraints for implementation:**
- `MUST hash passwords with argon2id`
- `MUST require passwords of at least 8 characters that pass Password::uncompromised()`
- `MUST scope session cookies to the organisation subdomain (host-only)`
- `MUST invalidate other sessions when a password changes`
- `MUST use only private or presence broadcast channels, authorised by the same Policies, with the organisation id in the channel name`
- `MUST NOT send correct answers or correctness to students before the live session finishes`
- `MUST NOT broadcast personal data other than nickname and avatar`
- `MUST rate-limit login to 5 attempts per minute per email`
- `MUST rate-limit enrolment key attempts to 10 per minute per user`
- `MUST rate-limit organisation registration to 3 successful registrations per hour per IP; submissions rejected by validation do not count`
- `MUST rate-limit the live subdomain availability check to 60 requests per minute per IP`
- `MUST write an audit entry for organisation registration, grade changes, role changes, user deactivation and reactivation, organisation settings changes, course space archiving and platform admin actions`
- `MUST accept logo uploads only as png, jpg or sanitised svg up to 2 MB and CSV uploads up to 1 MB, stored outside the public directory`
- `MUST run composer audit and npm audit in CI`

_since v0.1.0_

### SEC-011 — Authentication (official Vue starter kit) · decided

> Engineer: “1 α”

**Decision:** Authentication comes from the official Laravel Vue starter kit (Inertia + Vue + TypeScript, auth on Laravel Fortify) instead of the unmaintained Breeze. Email and password; TOTP MFA via Fortify, required for platform admins, optional for org admins, not offered to teachers and students; SSO via Socialite deferred (EXT-001)

**Why:** Breeze receives no updates since Laravel 12; the starter kit is its official successor and Fortify provides two-factor authentication

**Rejected:**
- Laravel Breeze: no longer maintained

**Constraints for implementation:**
- `MUST require TOTP MFA for ROLE-PlatformAdmin`
- `MUST offer optional TOTP MFA to ROLE-OrgAdmin`

_supersedes SEC-001 · since v0.1.0_

### SEC-012 — Optional MFA for org admins · decided · source: inferred

**Decision:** TOTP MFA through Fortify (confirm: true, confirmPassword: true), offered only to org admins. The secret and recovery codes are stored encrypted on the user. The two-factor challenge at login is limited to 5 attempts per minute per login session. When a user stops being an org admin, their MFA is cleared in the same transaction. No admin may reset another admin's MFA in this phase

**Why:** Closes the MFA gaps of SEC-011 found in CR-P2-T2-1

**Constraints for implementation:**
- `MUST let only org admins enable or disable MFA; other roles get 403 and the settings are hidden`
- `MUST require the current password and a valid first TOTP code to enable MFA`
- `MUST rate-limit the two-factor challenge to 5 attempts per minute per login session`
- `MUST clear two_factor_secret, two_factor_recovery_codes and two_factor_confirmed_at in the same transaction that changes a user's role away from org_admin`

_affects UC-006, P2-T2 · since v2.1.0_

## Architecture

### ARCH-001 — Technology stack · decided

> Engineer: “Backend: Laravel
Frontend: Inertia.js + Vue 3
Βάση: PostgreSQL (Class Table Inheritance, JSONB μόνο για άμορφα δεδομένα)
Real-time: Laravel Reverb + Laravel Echo
Async: Laravel Queues + Jobs + Scheduler
Cache / queue / broadcast: Redis
”

**Decision:** Laravel backend, Inertia.js + Vue 3 frontend, PostgreSQL database, Laravel Reverb + Echo for real-time, Laravel Queues/Jobs/Scheduler for async work, Redis for cache, queue and broadcasting

**Why:** Engineer's choice; continuation of a simpler thesis system

_since v0.1.0_

### ARCH-002 — Multi-tenancy model · decided

> Engineer: “Tenancy: row-level με organization_id και global scope, subdomain ανά οργανισμό”

**Decision:** Single database, row-level tenancy: every tenant-owned table has organization_id, enforced by an Eloquent global scope; each organisation is reached on its own subdomain

_since v0.1.0_

### ARCH-003 — Code structure · decided

> Engineer: “Αρχιτεκτονική: Actions/Services με λεπτούς controllers, event-driven κορμός (domain events → listeners)”

**Decision:** Thin controllers delegate to Actions/Services; side effects are driven by domain events handled by listeners

_since v0.1.0_

### ARCH-004 — Modular monolith · decided · source: inferred

**Decision:** One deployable Laravel application split into 11 modules (see components) with dependencies enforced by deptrac; activity types plug into Activities through an ActivityType contract

**Constraints for implementation:**
- `MUST respect component may_depend_on rules; deptrac fails the build otherwise`
- `MUST add new activity types by implementing the ActivityType contract, not by editing the Activities module`

_since v0.1.0_

### ARCH-005 — Versions and frontend language · decided

> Engineer: “2 TypeScript | 4 έχω 8.3 php στο παρόν μηχάνημα τα υπόλοιπα ειναι οκ (σε docker δεν υπάρχει θέμα), προεπιλογές oκ”

**Decision:** PHP 8.3, Laravel 13, Inertia 3 (or the version the starter kit ships), Vue 3.5 with TypeScript, Tailwind 4, PostgreSQL 18, Redis 8, Reverb 1. Exact versions are pinned by lockfiles in phase 1

**Constraints for implementation:**
- `MUST write frontend code in TypeScript`
- `MUST keep code compatible with PHP 8.3`

_since v0.1.0_

### ARCH-006 — Real-time and integration policies · decided · source: default

**Decision:** Live answers go over HTTP, never websocket client events; the server alone decides time; Reverb outage falls back to HTTP polling every 2 seconds for students and teacher; integration logs carry ids only; email is queued with 5 retries

**Constraints for implementation:**
- `MUST submit live answers through HTTP endpoints, never websocket client events (whisper)`
- `MUST derive client countdowns from server-sent ends_at and server_now`
- `MUST fall back to HTTP polling every 2 seconds when the websocket connection is lost`
- `MUST log external calls with ids only, never emails or names`

_affects CMP-Live, CMP-Notifications, INT-001 · since v0.1.0_

### ARCH-007 — Starter kit dependencies and dev tooling · decided

> Engineer: “1 A, 2 αφαίρεσε τα, 3 vitest ^4, 4 παμε με τα πακέτα του design (ESLint και Prettier), 5 οκ”

**Decision:** Keep the official Vue starter kit and shadcn-vue and approve their runtime packages (tinker, wayfinder, reka-ui, lucide, vueuse, class-variance-authority, clsx, tailwind-merge, tw-animate-css, vue-sonner, vue-input-otp); remove the kit's passkeys and chisel; use vitest ^4 to match the kit's vite 8; lint and format with ESLint and Prettier instead of the kit's vite-plus; during phase 1 dev-only packages may be added without a decision

**Why:** The starter kit and shadcn-vue were chosen in ARCH-005/SEC-011 but their dependencies were missing from the approved list (CR-P1-T1-1)

**Rejected:**
- Minimal skeleton without starter kit UI or shadcn-vue: rewrites auth screens and every UI component by hand
- Pin vite to 7 for vitest 3: diverges from the kit
- vite-plus: new and less known tool; the design already mandates ESLint and Prettier

**Constraints for implementation:**
- `MUST NOT install @laravel/passkeys or laravel/chisel; remove the starter kit features that use them`
- `MUST lint and format with ESLint and Prettier; MUST NOT use vite-plus`
- `MUST list every dev-only package added in phase 1 in the P1-T1 handoff`

_affects P1-T1 · since v1.1.0_

### ARCH-008 — Organisation authorisation through a Tenancy contract · decided · source: inferred

**Decision:** CMP-Tenancy defines App\Modules\Tenancy\Contracts\OrganizationMember (organizationId(): string, administersOrganization(): bool) and authorises organisation actions in its own OrganizationPolicy against that contract only; Identity's User implements it (administersOrganization() is role === org_admin)

**Why:** Tenancy may depend on no other component, yet UC-033 needs the user's role, which lives in Identity; the contract keeps the dependency direction Identity -> Tenancy (CR-P2-T3-1)

**Rejected:**
- Comparing the role as a string inside Tenancy: hidden dependency on Identity that deptrac cannot see
- Implementing the contract in P2-T2: lengthens the chain P2-T1 -> P2-T2 -> P2-T3
- Settings controller and policy in Identity: moves organisation settings out of their module

**Constraints for implementation:**
- `MUST authorise organisation settings in Tenancy's OrganizationPolicy through the OrganizationMember contract; MUST NOT reference Identity classes or role strings from Tenancy`
- `MUST allow updating an organisation's settings only to a user who implements OrganizationMember, belongs to that organisation and administers it`

_affects UC-033, P2-T3 · since v1.4.0_

## Operations

### OPS-001 — Production hosting · deferred · source: inferred

**Deferred to:** PHASE-9 · trigger: Core features work and the system is about to host real users (together with SEC-003)

**Requirements:**
- Data, backups and logs inside the EU (ORG-001)
- Free or very low cost (SCOPE-002)
- One server of ~2 vCPU / 4 GB RAM is enough for web, queue worker, scheduler, Reverb, PostgreSQL 18 and Redis 8 at the target scale
- Long-lived WebSocket connections allowed (rules out serverless and most shared hosting)
- Wildcard DNS (*.domain) and a wildcard TLS certificate (Let's Encrypt DNS-01 challenge, so a DNS provider with an API)
- Off-site EU storage for backups
- Meets the RPO, RTO and availability targets set by OPS-003

**Constraints for implementation:**
- `MUST read all configuration from environment variables`
- `MUST log structured JSON to stdout`
- `MUST access files only through Laravel Storage disks so an S3-compatible disk can replace the local one`
- `MUST NOT depend on provider-specific services in application code`
- `MUST keep live quiz state in PostgreSQL so a restart of Reverb or the queue worker loses no session`
- `MUST expose the Laravel health endpoint /up`
- `MUST NOT keep state on the server's local disk or memory: sessions, cache and queues in Redis, files through Storage disks`

_since v0.1.0_

### OPS-002 — Deploys and live quizzes · decided · source: inferred

**Decision:** A deploy waits until no live session is running

**Constraints for implementation:**
- `MUST make the deploy script refuse or wait while any live session has status running`

_since v0.1.0_

### OPS-003 — Operational targets (RPO, RTO, availability, backups) · deferred

> Engineer: “ίσως να αφήσουμε deferred και τους στόχους λειτουργίας μέχρι το πρώτο production τι λες ;”

**Deferred to:** PHASE-9 · trigger: Planning the first production deploy

**Requirements:**
- RPO (acceptable data loss)
- RTO (acceptable recovery time)
- Availability target
- Backup frequency, retention and restore-test cadence, consistent with the RPO

**Constraints for implementation:**
- `MUST NOT keep state on the server's local disk or memory: sessions, cache and queues in Redis, files through Storage disks`

_since v0.1.0_

### OPS-004 — Repository and CI · decided

> Engineer: “α, GitHub με GitHub Actions”

**Decision:** The repository lives on GitHub; CI runs on GitHub Actions (free tier). The design gate (.github/workflows/design.yml) exists from now; the application pipeline with the stages in operations.ci is created in the first phase

**Constraints for implementation:**
- `MUST run every operations.ci stage on each pull request and push to main`
- `MUST NOT merge a pull request whose CI fails`

_since v0.1.0_

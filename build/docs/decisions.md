<!-- GENERATED from design/ — do not edit. Rebuild with tools/design/build.py -->
# Decision record

_Generated document · design v1.4.0 · source cc1c2e1e847f_


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

## External

### DEC-001 — Email notifications · decided

> Engineer: “ανακοινώσεις, νέα δραστηριότητα (για χώρο μαθήματος που είναι εγγεγραμμένος, νέα βαθμολόγηση, έγγριση/απόρριψη θέματος, πρόσκληση σε ομάδα (δεν μου έρχεται κατι άλλο προς το παρόν)”

**Decision:** Besides account emails (invitation, password reset, verification), email is sent for: new announcement, new activity in an enrolled course space, new or final grade, topic approved or rejected, group invitation. Everything else is in-app only

**Constraints for implementation:**
- `MUST NOT send email for notification kinds other than those listed in DEC-001`

_affects EXT-002, UC-007, UC-008, UC-022, UC-026, UC-027, UC-019 · since v0.1.0_

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
- `MUST write an audit entry for organisation registration, grade changes, role changes, user deactivation, organisation settings changes and platform admin actions`
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

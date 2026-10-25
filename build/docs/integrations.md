<!-- GENERATED from design/ — do not edit. Rebuild with tools/design/build.py -->
# Integrations

_Generated document · design v3.5.0 · source 174b6708ecea_


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

## Flows

### FLOW-001 — Live quiz run (common time window, free navigation)

```mermaid
sequenceDiagram
  actor T as Teacher
  actor S as Student
  participant L as CMP-Live
  participant R as Reverb (presence + private channels)
  participant A as CMP-Assessment
  participant G as CMP-Gamification
  S->>L: join (enrolled? SEC-005)
  L-->>R: presence: student joined
  R-->>T: lobby / progress update
  alt manual start
    T->>L: Start
  else scheduled start
    L->>L: scheduled job at start time
  end
  L->>L: status running, ends_at = start + duration
  L->>L: schedule FinishSession job at ends_at
  L-->>R: SessionStarted(ends_at, server_now)
  R-->>S: questions (no correct answers) + countdown from server time
  loop until submit or ends_at
    S->>L: PUT answer for question k (create or change)
    L->>L: reject if after ends_at or already submitted
    L-->>R: Progress(student, answered count) to teacher channel only
    R-->>T: live progress
  end
  S->>L: submit early (optional)
  Note over L: ends_at reached or all joined students submitted
  L->>A: score answers with fixed points
  L->>L: status finished, final ranks
  L-->>R: SessionFinished
  R-->>S: own correctness, score, podium (if enabled)
  R-->>T: final ranking and summary
  L-)G: event LiveSessionFinished
  G->>G: XpEvent per participant
```

### FLOW-002 — Live quiz reconnection

```mermaid
sequenceDiagram
  actor S as Student
  participant L as CMP-Live
  participant R as Reverb
  R--xS: connection lost
  S->>S: show "reconnecting…", keep unsent answer locally, poll state every 2s over HTTP
  S->>L: GET session state
  L-->>S: snapshot(status, ends_at, server_now, own saved answers, submitted?)
  S->>L: resend unsent answer if still before ends_at
  S->>R: resubscribe when websocket is back
  S->>S: hide banner, continue with remaining time
```

### FLOW-003 — Invitation and account activation

```mermaid
sequenceDiagram
  actor A as Org admin
  participant I as CMP-Identity
  participant N as CMP-Notifications
  participant AU as CMP-Audit
  participant M as Email delivery
  actor T as Invitee
  A->>I: invite(email, role)
  I->>I: reject if email exists anywhere (SEC-004)
  I-)N: event InvitationCreated
  I-)AU: event InvitationCreated
  N->>M: queued InvitationNotification (retries on failure)
  M-->>T: email with single-use link (7 days)
  T->>I: accept(token, name, password)
  I->>I: activate user, mark email verified
```

## Decisions

_none_

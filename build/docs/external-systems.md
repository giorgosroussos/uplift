<!-- GENERATED from design/ — do not edit. Rebuild with tools/design/build.py -->
# External systems and constraints

_Generated document · design v1.4.0 · source cc1c2e1e847f_


## Context diagram

```mermaid
flowchart LR
  SYS["Uplift"]
  ACT_Visitor["👤 Visitor"] --> SYS
  ACT_Student["👤 Student"] --> SYS
  ACT_Teacher["👤 Teacher"] --> SYS
  ACT_OrgAdmin["👤 Organisation admin"] --> SYS
  ACT_SuperAdmin["👤 Platform super-admin"] --> SYS
  ACT_Scheduler(["Scheduler"]) --> SYS
  EXT_001[["Single sign-on identity providers (TBD vendor)"]]
  SYS <--> EXT_001
  EXT_002[["Email delivery (TBD vendor)"]]
  SYS --> EXT_002
```

### EXT-001 — Single sign-on identity providers _(deferred)_

Organisation SSO login (future) · direction: both · owner: third-party

Requirements for the choice:
- Providers not chosen yet (OAuth such as Google/Microsoft, possibly SAML)
- Per-organisation configuration
- Works through Laravel Socialite

Prepare now:
- MUST allow a user without a password (nullable password column) so SSO-only accounts are possible
- MUST resolve the organisation from the subdomain before authentication so login methods can be configured per organisation
- MUST NOT put provider-specific identity fields on the users table; external identities will live in their own table

| environment | mode | tool | config vars |
|---|---|---|---|
| development | tbd |  |  |

### EXT-002 — Email delivery _(deferred)_

Invitations, password reset, email verification and notifications · direction: outbound · owner: third-party

Requirements for the choice:
- Free tier or very low cost (SCOPE-002)
- SMTP or a Laravel mail driver
- Deliverability for transactional email (SPF/DKIM)

Prepare now:
- MUST send email only through Laravel Mail/Notifications, never a provider SDK directly
- MUST send email from queued jobs

| environment | mode | tool | config vars |
|---|---|---|---|
| development | catcher | Mailpit | MAIL_* |

## Organisational constraints

| id | constraint |
|---|---|
| ORG-001 | τα δεδομένα να μένουν σε ΕΕ (λογικό για ανηλίκους και GDPR) |

## Decisions

| id | title | status | decision |
|---|---|---|---|
| DEC-001 | Email notifications | decided | Besides account emails (invitation, password reset, verification), email is sent for: new announcement, new activity in an enrolled course space, new or final grade, topic approved or rejected, group invitation. Everything else is in-app only |

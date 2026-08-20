# 04 — Traceability & Acceptance Criteria

## 1. Ticket Traceability

| Requirement | UI | Backend | Notification | Audit |
|---|---|---|---|---|
| Customer creates Ticket | Customer Dashboard/Create | Create Ticket API | Created event | Yes |
| IT Owner sees Ticket | Owner Dashboard | Customer-scope query | Assignment | Yes |
| Owner assigns Support | Ticket Detail | Assignment API | Support notified | Yes |
| Support resolves | Support Ticket | Resolve API | Customer + Owner | Yes |
| Customer confirms | Ticket Detail | Confirm API | Optional | Yes |
| Customer reopens | Reopen Modal | Reopen API | Owner + Lead + Support | Yes |
| SLA breach | Ticket/Lead Dashboard | SLA worker | Escalation | Yes |

## 2. Contract Traceability

| Requirement | UI | Backend | Notification | Audit |
|---|---|---|---|---|
| Contract signed | Contract Detail | Contract create | Optional | Yes |
| Customer sees contract | Portal | Scoped contract API | N/A | Yes |
| Alert #1 | Contract Detail/History | Alert worker | Email | Yes |
| Alert #2 | Contract Detail/History | Alert worker | Email | Yes |
| Alert #3 | Contract Detail/History | Alert worker | Email | Yes |
| Renewal | Contract Detail | Renewal API | Internal + customer policy | Yes |
| Expired | Dashboard | Status worker | Internal | Yes |

## 3. Acceptance Criteria — Ticket

### TC-TKT-001

Given Customer has a valid active Contract + Service, when Customer creates a Ticket, then system auto-selects Contract, calculates SLA, assigns IT Owner and records the creation event.

### TC-TKT-002

Given IT Owner assigns Support, when assignment succeeds, then assigned Support sees the Ticket in My Queue and receives notification.

### TC-TKT-003

Given Support resolves a Ticket, when required resolution fields are missing, then system blocks Resolve.

### TC-TKT-004

Given Ticket is Resolved, when Customer clicks Reopen, then Reopen Reason is mandatory, status becomes REOPENED, reopen_count increments and escalation notifications are created.

### TC-TKT-005

Given same Reopen event is retried by browser/API, then only one business event is created for the same request idempotency key.

## 4. Acceptance Criteria — Contract

### TC-CON-001

Given contract is Active and alert rule is 30 days before expiry, when scheduler reaches trigger time, then one Pending alert instance is created and dispatched.

### TC-CON-002

Given Alert #1 is already SENT for an expiry date, when scheduler runs again, then system does not send duplicate email.

### TC-CON-003

Given email provider returns failure, then alert status becomes FAILED, retry_count increments and error is stored.

### TC-CON-004

Given Customer Portal requests contract list, then system returns only contracts belonging to authenticated Customer and only whitelisted fields.

### TC-CON-005

Given contract is renewed, then old contract remains immutable/history-safe and new period/version is linked to prior contract.

## 5. Security acceptance

- Customer A cannot query Customer B ticket/contract by changing URL ID.
- Internal notes are never returned to Customer Portal API.
- Contract commercial fields are permission-gated.
- Audit records cannot be modified by normal operational roles.

## 6. Operational acceptance

- Scheduler restart does not duplicate alerts.
- Email worker restart does not lose Pending alerts.
- Bitrix24 outage does not block ticket resolution.
- All failed integrations can be retried.

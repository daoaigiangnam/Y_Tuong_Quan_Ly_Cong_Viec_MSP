# Module 07 — Contract Management

## 1. Purpose

Contract Management is the commercial and operational control layer connecting Customer, Services, SLA and Ticket operations.

The module prevents expired contracts from becoming invisible operational risks and provides a single source of truth for:

- Contract identity and validity.
- Contract type.
- Customer ownership.
- Covered services.
- Responsible IT Owner and IT Lead.
- Sales owner.
- Alert schedule and alert history.
- Customer-visible contract information.

## 2. Product Principle

```text
Customer
   |
   +---- Contract
          |
          +---- Services
          +---- SLA / operational rules
          +---- IT Owner
          +---- IT Lead
          +---- Sales
          +---- Alert Rules
          +---- Alert History
```

The Contract record is the authoritative source for contract validity. Ticket processing may reference a contract, but Ticket screens must not invent contract dates or ownership.

## 3. Contract Types

The product must support the current official options without hard-coding the architecture around them:

- FULL_PACKAGE — Trọn gói.
- PAY_PER_INCIDENT — Theo sự vụ phát sinh.

Future Product decisions may add:

- HOURLY.
- HYBRID.

Therefore service code should validate contract type through a policy/catalog boundary rather than scattering ENUM assumptions through UI code.

## 4. Contract Status

```text
DRAFT
  ↓
PENDING_SIGN
  ↓
ACTIVE
  ├── EXPIRING
  ├── EXPIRED
  ├── RENEWED
  └── CANCELLED
```

Status is controlled by server-side rules. A user cannot make an expired contract ACTIVE merely by changing a browser field.

## 5. Core Data

Existing contract schema contains:

- contract_no
- customer_id
- contract_type
- start_date
- end_date
- value
- status
- owner_user_id
- lead_user_id
- sales_user_id
- public_notes
- internal_notes
- created_at / updated_at

Services are linked through `contract_services`.

## 6. Contract Detail

### Internal Portal

Contract detail must show:

1. Contract header.
2. Customer.
3. Contract type.
4. Start/end date.
5. Current status.
6. Value where authorized.
7. Covered Services.
8. IT Owner.
9. IT Lead.
10. Sales owner.
11. Alert configuration.
12. Alert history.
13. Internal notes.
14. Customer-visible information.
15. Audit history.
16. Contract PDF/file metadata when document storage is enabled.

### Customer Portal

Customer sees only customer-authorized information:

- Contract number.
- Contract type.
- Start date.
- End date.
- Status.
- Covered services.
- Customer-visible notes.
- Contract document when Product enables PDF access.

Customer must not see internal notes, internal audit data, internal recipients or internal escalation details.

## 7. Contract Lifecycle Operations

### Create Draft

Authorized internal user creates the contract with customer, dates, type and ownership.

### Activate

Activation validates:

- Customer exists and is active.
- Contract dates are valid.
- End date is not before start date.
- Contract type is supported.
- Covered service relationships are valid.
- Required owner/lead rules are satisfied according to Product configuration.

### Expiring

The system may calculate an EXPIRING operational state based on configurable policy. It must not alter the contractual end date.

### Expired

When the contract end date has passed and it has not been renewed/cancelled according to policy, the system marks it EXPIRED through a controlled process.

## 8. Alert Design

Default Product proposal:

```text
Alert 1 = 90 days before expiry
Alert 2 = 60 days before expiry
Alert 3 = 30 days before expiry
```

The system stores alert rules per contract. The alert engine reads `contract_alert_rules`; it does not hard-code 90/60/30 into the scheduler.

Existing tables:

- `contract_alert_rules` — configuration.
- `contract_alerts` — execution/audit instances.
- `email_logs` — email delivery audit.

Each alert records:

- alert_no
- scheduled_date
- attempted_at
- sent_at
- status
- recipient
- cc
- error_message
- email_log_id
- created_at

## 9. Alert Engine Flow

```text
Daily Scheduler
      ↓
Find ACTIVE / EXPIRING contracts
      ↓
Read contract_alert_rules
      ↓
Calculate scheduled date
      ↓
Create / reuse contract_alerts row
      ↓
Already SENT? ── YES → STOP
      │
      NO
      ↓
Already attempted today? ── YES → STOP
      │
      NO
      ↓
Resolve recipients server-side
      ↓
Send email
      ↓
Write email_logs
      ↓
Update contract_alerts
      ├── SENT + sent_at
      └── FAILED + error_message
```

A missed scheduler run can catch up an unsent alert because due instances are selected when their scheduled date is on or before the current business date. A failed attempt is not retried repeatedly on the same day; it becomes eligible again on a later scheduler run.

## 10. Alert Recipients

The agreed operational requirement is:

**Customer notification:** Customer is the primary recipient when a customer email is available.

**Internal visibility:**

- IT Owner.
- IT Lead.
- Sales owner of the contract.

Internal recipients are placed in CC when Customer email is available. If Customer email is unavailable, the service falls back to an internal recipient as TO and keeps the remaining internal recipients in CC.

The recipient list is generated server-side from Customer/Contract/User relationships. It is never accepted from the browser.

## 11. Three-Alert Traceability

The Contract list must make the three alerts auditable.

Recommended columns:

| Column | Example |
|---|---|
| Alert 1 | SENT — 2026-08-01 |
| Alert 2 | SENT — 2026-09-01 |
| Alert 3 | PENDING |

The UI should show status rather than merely a boolean so failures are visible.

## 12. Alert Idempotency and Retry

A scheduled alert must not be sent twice after it is marked SENT for the same contract and alert number.

The database uniqueness rule `contract_id + alert_no` is the primary instance protection. The service also checks `sent_at` before sending.

`attempted_at` prevents repeated sends during the same scheduler day. `FAILED` records remain retryable on a later day. This creates a traceable retry path without silently losing failed notifications.

## 13. Alert Failure

If email sending fails:

- Record FAILED.
- Store error_message.
- Store an email log.
- Keep alert auditable.
- Do not mark sent_at.
- Allow a later retry mechanism.

A failed email must never silently disappear.

## 14. Customer Portal UX

### Contract List

```text
Contract No | Type | Start | End | Status | Services | View
```

### Contract Detail

```text
[Contract Header]

Customer
Contract Type
Validity
Status

Covered Services

Customer-visible Information

[View Contract Document]
```

No internal alert recipient or internal note is exposed.

## 15. Internal Portal UX

### Contract Dashboard

Summary cards:

- Active contracts.
- Expiring soon.
- Expired.
- Alerts pending.
- Alerts failed.

### Contract List Filters

- Customer.
- Contract type.
- Status.
- Service.
- IT Owner.
- IT Lead.
- Sales.
- End-date range.
- Alert status.

### Contract Detail Actions

- Edit.
- Activate.
- Configure services.
- Configure alert schedule.
- View alert history.
- Retry failed alert when authorized.
- Upload/associate document when enabled.
- Renew.
- Cancel.

## 16. Shared UI Principle

Customer Portal and Internal Portal use the same visual language:

- Same Contract number.
- Same type labels.
- Same status badges.
- Same service display.
- Same date format.

Only data scope and actions differ through RBAC + customer scope.

## 17. Contract ↔ Ticket

Ticket creation may resolve a contract by:

```text
Customer
 + Service
 + Ticket date
      ↓
Eligible active contract
      ↓
Contract selected / resolved
      ↓
SLA resolution
```

A Ticket must not be considered contract-covered merely because the Customer has some other active contract.

## 18. Contract ↔ SLA

The exact Product decision remains configurable:

- SLA by Service.
- SLA by Contract + Service.

The Contract module therefore exposes the contract/service relationship and does not hard-code SLA selection logic.

## 19. Contract Documents

PDF/document visibility remains a Product Decision.

The data model and authorization boundary should support:

- Internal-only document.
- Customer-visible document.

Raw filesystem paths must never be exposed directly.

## 20. Security

Every contract operation must verify:

```text
Authenticated
   ↓
Permission
   ↓
Customer / organizational scope
   ↓
Contract state
   ↓
Execute
```

Customer Portal access is restricted to the customer's own contracts.

Internal users can only access contracts allowed by their RBAC/data scope.

## 21. Audit

Audit at minimum:

- Created.
- Updated.
- Status changed.
- Type changed.
- Service linked/unlinked.
- Owner changed.
- Lead changed.
- Sales changed.
- Alert rule changed.
- Alert sent/failed/retried.
- Document uploaded/visibility changed.
- Renewed.
- Cancelled.

## 22. Acceptance Criteria

- AC-CON-001 Contract number is unique and immutable.
- AC-CON-002 Customer is required.
- AC-CON-003 Start/end dates are valid.
- AC-CON-004 Contract type is validated through policy/catalog rules.
- AC-CON-005 Contract services can be maintained only by authorized users.
- AC-CON-006 Customer Portal shows only its own authorized contracts.
- AC-CON-007 Customer Portal never exposes internal notes.
- AC-CON-008 Alert rules are configurable per contract.
- AC-CON-009 Default schedule can represent 90/60/30 days.
- AC-CON-010 Each alert is idempotent.
- AC-CON-011 Alert failure is persisted as FAILED.
- AC-CON-012 Customer + IT Owner + IT Lead + Sales routing is generated server-side.
- AC-CON-013 Alert history records attempted/sent date/time.
- AC-CON-014 Contract status transitions are validated server-side.
- AC-CON-015 Renewed/expired contracts remain historically traceable.
- AC-CON-016 Contract scope is enforced on every read/write endpoint.
- AC-CON-017 Email delivery attempts are persisted in `email_logs`.
- AC-CON-018 Same-day duplicate alert attempts are blocked.
- AC-CON-019 Failed alerts can become eligible for a later retry.

## 23. Test Gate

Module 07 is not DONE until:

- Documentation PASS.
- Contract validation PASS.
- Lifecycle tests PASS.
- Customer scope tests PASS.
- Service relationship tests PASS.
- Alert schedule tests PASS.
- Alert idempotency tests PASS.
- Alert failure tests PASS.
- Recipient routing tests PASS.
- MySQL integration PASS.
- Alert Engine MySQL integration PASS.
- Regression Modules 01–06 PASS.
- GitHub Actions GREEN.

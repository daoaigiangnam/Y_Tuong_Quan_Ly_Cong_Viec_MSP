# Module 08 — Problem Management

## 1. Purpose

Problem Management identifies, investigates and permanently addresses the underlying causes of recurring or high-impact Incidents.

The module separates:

- **Incident** — restore service quickly.
- **Problem** — identify and control the underlying cause.
- **Known Error** — a Problem whose root cause is understood and for which a workaround may exist.
- **Change** — controlled implementation of a permanent fix when required.

Problem Management is part of the Advanced ITSM phase and must integrate with the existing Ticket, Customer, Contract, Service, SLA and RBAC domains without duplicating them.

## 2. Product Principle

```text
Incident / Ticket
      ↓
Problem Candidate
      ↓
Problem Investigation
      ↓
Root Cause Analysis
      ↓
Known Error? ── YES → Workaround
      │
      NO
      ↓
Permanent Fix Required
      ↓
Change / Implementation
      ↓
Validation
      ↓
Problem Resolved / Closed
```

The Problem record is the authoritative record for root-cause investigation. Tickets remain the authoritative records for customer-impacting incidents.

## 3. Problem Types

Initial supported values:

- REACTIVE — created from one or more incidents after service impact.
- PROACTIVE — created from trend, monitoring, capacity, risk or recurring-event analysis.

Future Product decisions may add additional classifications through a policy/catalog boundary rather than hard-coded UI logic.

## 4. Problem Status

```text
NEW
  ↓
ASSESSING
  ↓
INVESTIGATING
  ↓
ROOT_CAUSE_IDENTIFIED
  ↓
KNOWN_ERROR
  ↓
FIX_PLANNED
  ↓
FIX_IMPLEMENTED
  ↓
VALIDATING
  ↓
RESOLVED
  ↓
CLOSED
```

Alternative paths:

```text
ASSESSING → REJECTED
INVESTIGATING → KNOWN_ERROR
KNOWN_ERROR → FIX_PLANNED
FIX_PLANNED → CANCELLED
```

Status transitions are controlled server-side. Browser-submitted status values must never bypass transition rules or permissions.

## 5. Core Data

The Problem domain should contain at minimum:

- problem_no
- title
- description
- problem_type
- priority
- status
- customer_id nullable for internal/global problems
- service_id nullable where the problem is service-specific
- owner_user_id
- lead_user_id
- root_cause
- workaround
- permanent_fix
- impact_summary
- discovered_at
- root_cause_identified_at
- resolved_at
- closed_at
- created_by_user_id
- created_at / updated_at

Related tables:

- `problem_tickets` — links Problems to existing Tickets.
- `problem_documents` — stores document metadata and visibility.
- `problem_history` — lifecycle and investigation history.
- `problem_workarounds` — reusable workaround records when required.

## 6. Problem Detail — Internal Portal

Problem detail must show:

1. Problem header.
2. Problem number.
3. Type.
4. Priority.
5. Status.
6. Customer and scope.
7. Service.
8. Owner / Lead.
9. Impact summary.
10. Linked Tickets.
11. Investigation timeline.
12. Root cause.
13. Workaround / Known Error information.
14. Permanent fix.
15. Linked Change reference when available.
16. Documents.
17. Audit history.

Customer Portal does not expose internal Problem Management data by default. A future Product policy may expose selected customer-safe Problem/known-error information.

## 7. Problem Creation

A Problem may be created from:

- An Incident/Ticket.
- Multiple recurring Tickets.
- SLA breach trends.
- Monitoring or operational observation.
- Capacity/performance analysis.
- Manual proactive investigation.

When created from a Ticket, the relationship must be stored in `problem_tickets`; the Ticket itself remains unchanged except for an auditable relationship event where appropriate.

Duplicate Problems should be detected by authorized users using customer/service/title/root-cause context. The system must not silently merge records.

## 8. Investigation

Investigation must preserve a traceable timeline.

Recommended investigation events:

- Created.
- Ticket linked/unlinked.
- Owner assigned/changed.
- Investigation started.
- Hypothesis recorded.
- Evidence added.
- Root cause updated.
- Workaround added/changed.
- Known Error declared.
- Permanent fix proposed.
- Change linked.
- Validation started/completed.
- Resolved.
- Closed/reopened.

Every mutation affecting the lifecycle or technical conclusion must be auditable.

## 9. Root Cause Analysis

Root cause is a controlled business/technical conclusion, not a free-form status flag.

The service should support:

- Root-cause summary.
- Technical evidence.
- Contributing factors.
- Detection gap.
- Preventive action.
- Corrective action.

The system must not mark a Problem `RESOLVED` solely because a workaround exists.

## 10. Known Error

A Problem becomes a Known Error when:

- The underlying cause is sufficiently understood according to policy.
- A workaround may be documented, or the operational response is known.
- The record is explicitly marked by an authorized user.

Known Error information should support fast Incident resolution without exposing internal investigation details to unauthorized customers.

## 11. Change Integration

A permanent technical fix may require a Change.

The Problem module stores the relationship to the Change record but does not duplicate Change workflow logic.

```text
Problem
  ↓
Permanent Fix
  ↓
Change
  ↓
Implementation
  ↓
Validation
  ↓
Problem Resolution
```

The Change module remains the system of record for implementation approval, scheduling and execution.

## 12. Ticket Integration

A Ticket may be linked to a Problem when:

- It is caused by the same underlying issue.
- It is part of the same recurring failure pattern.
- It provides investigation evidence.

A Ticket may reference only Problems within the user's authorized scope.

Closing a Problem must not automatically close linked Tickets unless a separate policy explicitly enables that behavior.

## 13. Priority and Impact

Problem priority should consider:

- Business impact.
- Frequency.
- Number of affected customers/users.
- Service criticality.
- Recurrence.
- Risk of recurrence.

The initial implementation may use a controlled priority catalog such as:

- P1 — Critical.
- P2 — High.
- P3 — Medium.
- P4 — Low.

Priority rules must remain server-side and auditable.

## 14. Problem Documents

Every Problem module implementation must include a documented document policy.

Supported document metadata should include:

- Original filename.
- Storage key/path identifier.
- MIME type.
- File size.
- Uploaded by.
- Uploaded at.
- Visibility.
- Optional document category.

Recommended visibility values:

- INTERNAL_ONLY.
- CUSTOMER_VISIBLE — only if Product policy explicitly permits customer access.

Raw filesystem paths must never be exposed as public URLs.

Documents must be authorization-checked through Problem scope before download.

## 15. Security and Scope

Every Problem operation follows:

```text
Authenticated
   ↓
Permission
   ↓
Customer / organizational scope
   ↓
Problem state
   ↓
Execute
```

Required permission concepts:

- `problem.view`
- `problem.create`
- `problem.update`
- `problem.assign`
- `problem.investigate`
- `problem.resolve`
- `problem.close`
- `problem.document.view`
- `problem.document.upload`
- `problem.document.delete`

Customer-scoped users must never access another customer's Problems or internal documents.

## 16. Audit

Audit at minimum:

- Problem created.
- Problem updated.
- Status changed.
- Priority changed.
- Owner/Lead changed.
- Ticket linked/unlinked.
- Root cause changed.
- Workaround changed.
- Known Error declared/revoked.
- Change linked/unlinked.
- Document uploaded/deleted.
- Document visibility changed.
- Problem resolved/reopened/closed.

## 17. Dashboard and List UX

### Problem Dashboard

Summary cards:

- Open Problems.
- Critical/High Problems.
- Problems under investigation.
- Known Errors.
- Problems awaiting Change.
- Overdue investigation targets.
- Recently resolved.

### Problem List

Recommended columns:

```text
Problem No | Title | Type | Priority | Status | Customer | Service | Owner | Updated | View
```

Filters:

- Status.
- Type.
- Priority.
- Customer.
- Service.
- Owner.
- Lead.
- Date range.
- Known Error.

## 18. Acceptance Criteria

- AC-PRB-001 Problem number is unique and immutable.
- AC-PRB-002 Problem title and description are required.
- AC-PRB-003 Problem type is validated server-side.
- AC-PRB-004 Problem priority is validated server-side.
- AC-PRB-005 Problem status transitions follow the allowed state machine.
- AC-PRB-006 Authorized users can link/unlink Tickets.
- AC-PRB-007 Ticket links preserve traceability.
- AC-PRB-008 Root cause changes are auditable.
- AC-PRB-009 Known Error declaration requires the required permission.
- AC-PRB-010 A workaround alone cannot mark a Problem RESOLVED unless policy explicitly permits it.
- AC-PRB-011 Change references are stored without duplicating Change workflow.
- AC-PRB-012 Customer scope is enforced on every Problem read/write operation.
- AC-PRB-013 Internal documents are never exposed to unauthorized users.
- AC-PRB-014 Document metadata is stored without exposing raw filesystem paths.
- AC-PRB-015 Document upload/delete operations are audited.
- AC-PRB-016 Resolved/closed Problems remain historically traceable.
- AC-PRB-017 Reopen is controlled by permission and state policy.
- AC-PRB-018 All lifecycle mutations are recorded in Problem history/audit logs.

## 19. Test Gate

Module 08 is not DONE until:

- Documentation PASS.
- Problem validation PASS.
- Lifecycle/state-machine tests PASS.
- Ticket relationship tests PASS.
- Root-cause / Known Error tests PASS.
- Permission tests PASS.
- Customer/object scope tests PASS.
- Document authorization tests PASS.
- MySQL integration PASS.
- Regression Modules 01–07 PASS.
- PHP lint PASS.
- GitHub Actions GREEN.

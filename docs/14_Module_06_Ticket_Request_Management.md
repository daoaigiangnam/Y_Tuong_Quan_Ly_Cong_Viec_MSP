# Module 06 — Ticket / Request Management

## 1. Purpose

Ticket Management is the operational core of the MSP platform. It provides one Ticket Engine shared by the Customer Portal and Internal Portal while authorization controls what each actor can see and do.

The module converts a customer request into a traceable work item linked to Customer, Contract, Service, SLA, Priority and assigned staff.

## 2. Product Principle

```text
Customer Portal                 Internal Portal
      |                               |
      +---------- Shared Ticket Engine+
                      |
               RBAC + Data Scope
```

There are NOT separate Ticket databases for Customer and Internal users.

## 3. Actors

- Customer User: create/view own permitted tickets, add notes/files, confirm resolution, reopen resolved ticket where allowed.
- IT Owner / Account Owner: see tickets within assigned customer scope, triage, assign to support staff, monitor SLA.
- IT Support: see assigned tickets, work the ticket, add internal/customer-visible updates, resolve.
- IT Lead: broader team/customer scope, escalation and oversight.
- Authorized Manager: configurable administrative access.

## 4. Ticket Lifecycle

```text
NEW
 ↓
TRIAGED
 ↓
ASSIGNED
 ↓
IN_PROGRESS
 ↓
PENDING_CUSTOMER / PENDING_VENDOR / PENDING_INTERNAL
 ↓
RESOLVED
 ├── Customer confirms → CLOSED
 └── Customer rejects → REOPENED → IN_PROGRESS
```

Transitions are server-side rules, not UI-only behavior.

## 5. Core Ticket Fields

| Field | Rule |
|---|---|
| ticket_no | System generated, immutable, unique |
| customer_id | Required |
| contract_id | Required or policy-defined exception |
| service_id | Required |
| request_type | Required from catalog |
| subject | Required, 5–255 chars |
| description | Required |
| priority | Required, controlled catalog |
| status | Controlled lifecycle |
| requester_user_id | Required for portal-originated request |
| owner_user_id | Account/IT Owner; nullable during triage |
| assignee_user_id | Support assignee; nullable during triage |
| sla_policy_id/version | Resolved from SLA engine |
| first_response_at | System timestamp |
| resolved_at | System timestamp |
| closed_at | System timestamp |
| created_at / updated_at | System timestamps |

## 6. Ticket Number

Format is configurable but must be unique and generated server-side. Example:

`INC-2026-000001`

The Product must not depend on a specific prefix being hard-coded.

## 7. Ticket Creation

### Customer Portal

1. Customer authenticates.
2. Selects Service.
3. System filters Contracts available to that Customer/Service.
4. Customer enters subject/description/priority if permitted.
5. System validates Customer scope.
6. System resolves applicable Contract and SLA.
7. Ticket is created as NEW.
8. Notification is queued.

### Internal Portal

Authorized internal users may create a ticket for a permitted Customer. Scope must be validated server-side.

## 8. Customer Priority Decision

Customer priority selection is a Product Decision. Therefore the UI must support both configurations:

- Customer can select priority.
- Customer cannot select priority; system/default or internal triage determines it.

Never trust a priority value from the browser.

## 9. Triage / Assignment

```text
NEW
 ↓
IT Owner sees Customer ticket
 ↓
Review Contract + Service + SLA
 ↓
Assign IT Support
 ↓
ASSIGNED
 ↓
IT Support sees ticket immediately
```

Assignment must record actor, old assignee, new assignee, timestamp and reason/note when supplied.

## 10. Ticket Timeline

Every important event becomes a timeline event:

- Created.
- Priority changed.
- Status changed.
- Owner assigned.
- Support assigned.
- Customer note.
- Internal note.
- Attachment added.
- SLA resolved/recalculated where permitted.
- Resolved.
- Customer confirmation.
- Reopened.
- Closed.
- Notification generated/sent/failed.

Timeline is append-only from the application perspective.

## 11. Internal vs Customer-visible Notes

Notes have visibility:

- `CUSTOMER_VISIBLE`
- `INTERNAL_ONLY`

Customer users must NEVER receive internal-only notes through Portal API, page rendering, email templates or attachments linked only to internal notes.

## 12. Attachments

Attachment metadata must be associated with a Ticket and visibility context. File access must use authorized download endpoints; raw storage paths must not be exposed.

Initial controls:

- Authentication required.
- Ticket scope required.
- File existence required.
- Size/type limits configurable.
- Audit download where required.

## 13. SLA Integration

At creation/triage, Ticket calls the SLA Resolver with:

```text
customer_id
contract_id
service_id
priority
occurred_at
```

The Ticket stores the resolved policy/version and calculated target timestamps.

Future SLA policy changes must not silently rewrite existing Ticket SLA.

## 14. Reopen Rule

Default supported flow:

```text
RESOLVED
   ↓
Customer checks result
   ↓
Not OK
   ↓
REOPENED
   ↓
Email Alert
 ├── IT Owner
 └── IT Lead
```

Reopen requires a customer-visible reason/note. The backend validates that the actor owns the ticket/customer scope and that the ticket is in a reopenable state.

Auto-close duration after RESOLVED remains configurable/Product Decision. No hard-coded number is treated as final.

## 15. Notifications

Notification events are decoupled from the Ticket transaction through a notification service/queue boundary.

Initial events:

- Ticket created.
- Assignment changed.
- Status changed.
- Resolved.
- Customer reopened.
- SLA breach/near-breach when enabled.

Recipients are calculated from current scope and assignment, not trusted from browser input.

## 16. Portal UI / UX

### Customer Portal — Ticket List

Columns:

`Ticket No | Subject | Service | Priority | Status | Created | Updated | Action`

Customer sees only tickets allowed by customer scope.

### Customer Portal — Ticket Detail

Sections:

1. Header/status.
2. Request details.
3. Service/Contract summary.
4. Conversation/timeline filtered to customer-visible events.
5. Attachments.
6. Add reply.
7. Reopen/confirm resolution when allowed.

### Internal Portal — Ticket List

Columns:

`Ticket No | Customer | Service | Priority | SLA | Status | IT Owner | Assignee | Updated | Action`

Filters:

- Customer.
- Contract.
- Service.
- Priority.
- Status.
- SLA state.
- Owner.
- Assignee.
- Date range.

### Internal Portal — Ticket Detail

Sections:

1. Customer/Contract/Service context.
2. SLA panel.
3. Assignment panel.
4. Status/actions.
5. Internal timeline.
6. Customer-visible conversation.
7. Attachments.
8. Audit/history.

## 17. Shared Visual Language

Customer and Internal Portal use the same design system:

- Same ticket number format.
- Same status labels.
- Same priority labels.
- Same timeline visual language.
- Same attachment interaction pattern.
- Same responsive Bootstrap components.

Only allowed data and actions differ.

## 18. Security / Authorization

Every Ticket operation must verify:

```text
Authenticated?
    ↓
Portal allowed?
    ↓
Permission allowed?
    ↓
Customer / Service scope allowed?
    ↓
Ticket state allows action?
    ↓
Execute
```

Never authorize from hidden form fields or UI state.

## 19. Concurrency

Assignment and status changes must be transaction-safe. A stale form must not overwrite a newer assignment/status without server-side conflict handling.

## 20. Audit

Audit at minimum:

- create
- assignment
- status transition
- priority change
- contract/service change where allowed
- customer-visible/internal note
- reopen
- close
- attachment operations

## 21. API Boundary

The UI should call application services/controllers rather than embedding business rules in templates. A future external API can reuse the same application services.

Bitrix24 REST integration is intentionally OUT OF SCOPE for Module 06 MVP. A future adapter may synchronize Tickets without coupling the core Ticket Engine to Bitrix24.

## 22. Acceptance Criteria

- AC-TKT-001 Customer can create a valid Ticket within permitted scope.
- AC-TKT-002 Customer cannot create a Ticket for another Customer.
- AC-TKT-003 Internal user cannot access a Customer outside scope.
- AC-TKT-004 Ticket number is unique and immutable.
- AC-TKT-005 Contract/Service relationship is validated.
- AC-TKT-006 SLA is resolved deterministically.
- AC-TKT-007 Assignment is recorded in history.
- AC-TKT-008 Internal notes are never visible to Customer Portal.
- AC-TKT-009 Customer-visible updates are visible to authorized Customer users.
- AC-TKT-010 Invalid lifecycle transitions are rejected.
- AC-TKT-011 Customer can reopen only a permitted resolved Ticket and must provide a reason.
- AC-TKT-012 Reopen creates an alert event for IT Owner and IT Lead when enabled.
- AC-TKT-013 Customer cannot directly change protected fields such as owner, assignee or SLA.
- AC-TKT-014 Attachment download requires authorization.
- AC-TKT-015 All critical transitions are audited.
- AC-TKT-016 Regression tests for Customer, Service, Contract, SLA and RBAC remain green.

## 23. Test Gate

Module 06 is not DONE until:

- Documentation exists and matches implementation.
- PHP lint PASS.
- Ticket validation PASS.
- Lifecycle transition tests PASS.
- Customer scope tests PASS.
- Internal scope tests PASS.
- Note visibility tests PASS.
- Assignment tests PASS.
- SLA integration tests PASS.
- Reopen tests PASS.
- Notification event tests PASS.
- Attachment authorization tests PASS.
- Audit tests PASS.
- Regression Modules 01–05 PASS.
- GitHub Actions GREEN.

# 11 — Module 03: Contract Management

## Status

`IN DEVELOPMENT`

## Purpose

Contract Management là lớp nghiệp vụ trung tâm nối Customer với Service và là nguồn tham chiếu cho SLA, Contract Alert, Assignment và Customer Portal visibility.

Module phải **configuration-driven**: Contract Type, SLA Scope, Alert Profile, Recipient Policy và Document Visibility không được hard-code nếu có thể thay đổi theo quyết định BOD/Product.

## Scope

- Contract Master CRUD.
- Contract Type.
- Contract lifecycle/status.
- Effective start/end dates.
- Customer and contacts.
- Contracted Services.
- Owner / Sales reference.
- Alert Profile reference.
- SLA Policy reference.
- Contract documents metadata.
- Customer visibility policy.
- Audit trail.
- Expiry dashboard/search.

## Contract Lifecycle

```text
DRAFT
  ↓
PENDING_APPROVAL
  ↓
ACTIVE
  ├── SUSPENDED
  └── EXPIRING
        ↓
      EXPIRED

DRAFT / PENDING_APPROVAL may be CANCELLED.
```

Status transitions must be validated server-side. Expiry is date-driven; the UI must not allow a user to manually mark a contract `EXPIRED` when the effective date has not passed.

## Contract Types

The architecture must support at minimum:

- `FULL_PACKAGE`
- `PER_INCIDENT`
- `HOURLY`
- `HYBRID`

Future types are data/configuration additions where no new calculation engine is required.

## Core Fields

| Field | Required | Rule |
|---|---:|---|
| contract_code | Yes | Unique |
| customer_id | Yes | Existing Customer |
| contract_type_id | Yes | Active Contract Type |
| title | Yes | 2–200 chars |
| start_date | Yes | Valid date |
| end_date | Yes | >= start_date |
| status | Yes | Controlled lifecycle |
| owner_user_id | No | Authorized internal user |
| sales_user_id | No | Authorized internal user |
| sla_policy_id | No | Configurable reference |
| alert_profile_id | No | Configurable reference |
| customer_visibility_policy | Yes | Controlled enum |
| notes | No | Internal only |

## Contract Services

A Contract may contain multiple Services and a Service may appear in multiple Contracts.

```text
Customer
   │
Contract
   │
   ├── Contract Service A
   ├── Contract Service B
   └── Contract Service C
```

Do not copy the full Service master record into the Contract. Use a relationship table and store contract-specific attributes there, such as:

- service scope description
- service status
- effective dates
- service-specific SLA override if approved
- service owner/assignment reference if approved

## SLA Policy

Contract may reference an SLA Policy. The exact precedence between global/service/contract policy remains configurable and must be recorded in the Product Decision Register before SLA Engine release.

Recommended resolution boundary:

```text
Customer + Contract + Service + Priority
                    ↓
             SLA Policy Engine
                    ↓
        Response / Resolution Targets
```

## Contract Alert

Contract stores the Alert Profile reference. Alert instances must be persisted separately so sent dates remain historically accurate.

Default example:

```text
STANDARD
#1 = 90 days
#2 = 60 days
#3 = 30 days
```

The system must support future profiles such as 120/60/30 without code changes to the Contract CRUD module.

Each sent alert records:

- contract_id
- alert_profile/version
- alert stage
- scheduled date
- sent date/time
- recipient snapshot
- delivery status
- provider/message identifier when available

Duplicate alerts must be prevented for the same Contract + alert instance.

## Recipients

Recipients are policy-driven. Potential recipients:

- Contract Owner
- Service Owner
- Team Lead
- Sales Owner
- Customer Contact
- configured additional recipient

Do not hard-code recipient addresses in controllers.

## Documents

Contract documents are metadata records attached to a Contract.

Minimum metadata:

- document name
- document type
- storage reference
- version
- uploaded by
- uploaded at
- visibility policy

Visibility policies:

- `INTERNAL_ONLY`
- `CUSTOMER_VIEW`
- `CUSTOMER_DOWNLOAD`
- `METADATA_ONLY`

Backend authorization is mandatory; hiding buttons is insufficient.

## Customer Portal

Customer Portal sees only contracts belonging to the authenticated Customer scope.

Customer view should expose, according to policy:

- Contract code
- Title
- Contract type display name
- Start/end date
- Status
- Contracted Services
- permitted documents

Internal notes, recipient configuration and internal audit data are never returned to Customer Portal.

## Internal Service Portal

Authorized internal users may see:

- full contract information within scope
- services
- SLA references
- alert status
- owner/sales references according to permission
- document metadata and permitted files
- audit history

The same shared design system should be used as Customer Portal while navigation, fields and actions are permission/scope driven.

## Screens

### CONT-01 Contract List

Columns:

`Contract Code | Customer | Type | Start | End | Status | Services | Owner | Alert Status | Updated | Actions`

Filters:

- Customer
- Contract Type
- Status
- Start/End date
- Expiring within N days
- Owner
- Sales
- Service
- Alert status

Quick views:

- Active
- Expiring
- Expired
- Draft
- Suspended

### CONT-02 Contract Create/Edit

Sections:

1. Contract identity.
2. Customer.
3. Contract type.
4. Dates/lifecycle.
5. Services.
6. Owners.
7. SLA Policy.
8. Alert Profile.
9. Documents.
10. Customer visibility.
11. Internal notes.

The UI must only show fields allowed by the current user's permission and policy.

### CONT-03 Contract Detail

Tabs:

1. Overview.
2. Services.
3. SLA.
4. Alerts.
5. Documents.
6. Customer View.
7. Audit.

## Validation

- Contract code unique.
- Customer must exist and be eligible.
- Contract type must be active.
- End date cannot precede start date.
- Active contract requires valid effective dates.
- Contract Service must reference an active Service unless an explicit historical exception is supported.
- Owner/Sales users must be active and authorized.
- Visibility policy must be validated server-side.
- Mutating requests require CSRF protection.

## Audit

Record at minimum:

- create
- update
- status transition
- service attach/detach
- owner change
- SLA reference change
- alert profile change
- document upload/remove/visibility change

For sensitive changes, store before/after values or structured change metadata.

## Acceptance Criteria

### AC-CONT-001
Authorized internal user can create a valid Draft Contract for an existing Customer.

### AC-CONT-002
Duplicate Contract Code is rejected without partial persistence.

### AC-CONT-003
Invalid date range is rejected server-side.

### AC-CONT-004
Contract can reference multiple Services without duplicating Service master records.

### AC-CONT-005
Customer Portal user can only see contracts belonging to their Customer scope.

### AC-CONT-006
Customer Portal cannot access internal notes, recipient configuration or restricted documents.

### AC-CONT-007
Contract Alert Profile is referenced rather than hard-coded into Contract business logic.

### AC-CONT-008
Contract document visibility is enforced by backend authorization.

### AC-CONT-009
Every contract mutation and important lifecycle/configuration change is auditable.

### AC-CONT-010
Existing Customer and Service module tests remain green after Contract module changes.

## Test Gate

Module 03 is not `DONE` until:

- PHP lint PASS.
- Clean DB/migration PASS.
- Contract CRUD tests PASS.
- Contract validation tests PASS.
- Contract-Service relationship tests PASS.
- Customer scope authorization tests PASS.
- Document visibility authorization tests PASS.
- Audit tests PASS.
- Customer regression PASS.
- Service regression PASS.
- GitHub Actions GREEN.

Only then may Module 04 — SLA / Policy Engine begin.

## Traceability

Primary decisions:

- DEC-001 Contract Type
- DEC-002 SLA Scope
- DEC-005 Contract Alert Schedule
- DEC-006 Alert Recipients
- DEC-007 Multiple Owner
- DEC-010 Contract PDF Visibility
- DEC-012 Shared Portal Experience

See `docs/08_Product_Decision_Register.md`.

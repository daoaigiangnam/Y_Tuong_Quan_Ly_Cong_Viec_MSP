# 09 — Module 01: Customer Management

## Status

`IN DEVELOPMENT`

## Objective

Xây dựng Customer Master làm nguồn dữ liệu trung tâm cho Contract, Service và Ticket. Module phải hỗ trợ Customer Portal nhưng không trộn Customer identity với Internal Service Portal identity.

## Scope

- Customer CRUD.
- Customer status.
- Customer code/unique identity.
- Customer contacts.
- Customer portal users.
- Service assignment scope.
- Customer search/filter.
- Customer detail.
- Audit history.
- RBAC/object scope.

## Core Model

```text
Customer
 ├── Contacts
 ├── Portal Users
 ├── Contracts
 ├── Services
 └── Tickets
```

## Customer Status

- `PROSPECT`
- `ACTIVE`
- `SUSPENDED`
- `INACTIVE`

Only `ACTIVE` customers can create new operational Tickets unless a specific policy overrides this behavior.

## Customer Fields

| Field | Required | Rule |
|---|---:|---|
| customer_code | Yes | Unique, immutable after creation |
| legal_name | Yes | 2–200 chars |
| display_name | Yes | 2–200 chars |
| tax_code | No | Unique when supplied |
| email | No | Valid email |
| phone | No | Normalized |
| address | No | Text |
| status | Yes | Controlled enum |
| notes | No | Internal only |
| created_at | System | Immutable |
| updated_at | System | Automatic |

## Customer Contact

A Customer may have multiple contacts.

Fields:

- customer_id
- full_name
- email
- phone
- title
- department
- is_primary
- is_active
- portal_access

Rules:

- One Customer may have many Contacts.
- At most one primary active Contact per Customer unless Product later enables multiple primary contacts by service.
- Portal access is independent from being the primary contact.

## Portal User

Customer Portal users must reference a Customer and optionally a Contact.

```text
User
 ├── portal_type = CUSTOMER
 ├── customer_id
 └── contact_id
```

Backend authorization must derive Customer scope from the authenticated user. Customer users must never be able to submit an arbitrary `customer_id` to access another Customer.

## Internal Users

Internal users may have Customer scope through role/group/assignment policy.

Do not encode customer access only in role names. Use permission + object scope.

## Screens

### CUST-01 Customer List

Columns:

`Code | Display Name | Tax Code | Status | Primary Contact | Active Contracts | Open Tickets | Updated | Actions`

Filters:

- Code/name.
- Status.
- Contract state.
- Has open tickets.
- Owner/group scope.

Actions:

- View.
- Edit.
- Activate.
- Suspend.
- Deactivate.

### CUST-02 Customer Detail

Tabs:

1. Overview.
2. Contacts.
3. Portal Users.
4. Contracts.
5. Services.
6. Tickets.
7. Audit.

### CUST-03 Contact Management

Allow authorized users to:

- Add contact.
- Edit contact.
- Deactivate contact.
- Enable/disable Customer Portal access.
- Resend portal invitation if notification module is enabled.

## Business Rules

1. Customer code cannot be duplicated.
2. Suspended/inactive customers cannot create new Tickets by default.
3. Existing Tickets remain readable to authorized users after suspension.
4. Deactivation must not cascade-delete contracts/tickets.
5. Contact deactivation must not delete historical Ticket authorship.
6. Portal access must be revocable without deleting the Contact.
7. Internal notes are never exposed to Customer Portal.

## API/Service Boundary

Recommended application services:

```text
CustomerService
CustomerContactService
CustomerPortalUserService
CustomerAuthorizationService
```

Controllers must not contain SQL-heavy business rules.

## Security

- Prepared statements only.
- CSRF on mutations.
- Authorization before object retrieval where possible.
- Escape output.
- Never trust submitted `customer_id` from Customer Portal.
- Audit create/update/status changes.

## Acceptance Criteria

### AC-CUST-001
Given an authorized internal user, when creating a Customer with a unique Customer Code, then the Customer is created and appears in Customer List.

### AC-CUST-002
Given a duplicate Customer Code, creation is rejected with a clear validation message and no partial record is persisted.

### AC-CUST-003
Given a Customer Portal user, the API only returns records belonging to that user's Customer scope.

### AC-CUST-004
Given an inactive Customer, Customer Portal cannot create a new Ticket unless an explicit policy allows it.

### AC-CUST-005
Given an authorized internal user, deactivating a Contact preserves historical Ticket references.

### AC-CUST-006
Given an unauthorized user, direct URL/API access to another Customer returns an authorization failure rather than exposing data.

### AC-CUST-007
All mutations generate audit records with actor, action, entity, entity_id, timestamp and relevant change metadata.

## Test Gate

Module cannot be marked `DONE` until:

- PHP lint PASS.
- Migration PASS on clean database.
- CRUD functional tests PASS.
- Duplicate/validation tests PASS.
- Customer scope authorization tests PASS.
- Portal access tests PASS.
- Audit tests PASS.
- UI checks PASS.
- Regression PASS.
- GitHub Actions GREEN.

Only after this gate passes may development proceed to Module 02 — Service Management.

# 13 — Module 05: User / Role / Permission / Portal Access

## Status

`IN DEVELOPMENT`

## Purpose

Module 05 provides the authorization foundation for the MSP platform. It separates **identity**, **role**, **permission**, and **scope** so the same application core can serve internal users and Customer Portal users without duplicating the business system.

## Product Principle

```text
                    CORE APPLICATION
                           │
                 Authentication Layer
                           │
                  Authorization Layer
                           │
             ┌─────────────┴─────────────┐
             │                           │
       CUSTOMER PORTAL             INTERNAL PORTAL
             │                           │
        Customer User          IT Owner / Support / Lead
        Contact User           Sales / Manager / Other
```

The UI can use the same design system and component library. What a user can see and do is controlled by role + permission + data scope.

## Actors

Initial role codes are examples and remain configurable:

- `SUPER_ADMIN`
- `IT_LEAD`
- `IT_OWNER`
- `IT_SUPPORT`
- `SALES`
- `CUSTOMER_ADMIN`
- `CUSTOMER_USER`

The authorization engine must not assume that every internal role is IT-specific. Future business functions can use the same RBAC engine.

## Identity Model

A User has:

- username
- full name
- email
- password hash
- role
- active/inactive state
- portal type
- customer scope where applicable
- last login
- created/updated timestamps

A Customer Portal user must reference a Customer. An Internal user must not be forced to reference a Customer.

## Role Model

Role = reusable bundle of permissions.

```text
Role
 ├── Role Code
 ├── Name
 ├── Portal Type
 └── Permissions
```

Example:

```text
IT_SUPPORT
 ├── ticket.view
 ├── ticket.update
 ├── ticket.comment
 └── attachment.manage
```

## Permission Model

Permission follows `resource.action`.

Examples:

- `customer.view`
- `customer.manage`
- `service.view`
- `contract.view`
- `contract.manage`
- `sla.view`
- `sla.manage`
- `user.view`
- `user.manage`
- `role.manage`
- `ticket.view`
- `ticket.update`
- `ticket.assign`
- `ticket.reopen`
- `audit.view`

Permission checks are always server-side.

## Scope Model

RBAC answers **what** a user may do. Scope answers **which records** the user may access.

Initial scopes:

```text
GLOBAL
CUSTOMER
SERVICE
ASSIGNED
```

Examples:

- Customer User → `CUSTOMER` scope for exactly one Customer.
- IT Owner → can be scoped to assigned Customers/Services.
- IT Lead → broader operational scope according to role configuration.
- Super Admin → `GLOBAL`.

A permission without an applicable data scope must not expose records.

## Portal Type

```text
INTERNAL
CUSTOMER
```

Customer Portal users must never reach internal administration screens merely because a URL is known.

Internal users must not automatically gain access to another Customer's portal identity records.

## Authorization Decision

Conceptual API:

```text
authorize(user, permission, resource, resourceContext)
```

Decision:

```text
ALLOW
DENY_NO_PERMISSION
DENY_SCOPE
DENY_PORTAL
DENY_INACTIVE
```

The application should fail closed.

## User Management UI

### USER-01 User List

Columns:

`Username | Full Name | Email | Role | Portal | Scope | Status | Last Login | Actions`

Filters:

- Status
- Role
- Portal Type
- Customer
- Search

### USER-02 User Detail

Tabs:

1. Profile
2. Role & Permissions
3. Scope
4. Portal Access
5. Security
6. Audit

### USER-03 Role Management

Columns:

`Code | Name | Portal | Users | Permissions | Status | Actions`

Role detail shows permission matrix by resource/action.

## Customer Portal UX

Customer Portal should feel like the same product, but only expose customer-safe navigation:

```text
Dashboard
Tickets
Contracts
Services
Contacts
Profile
```

No internal navigation, audit administration, global customer search, role management, or internal assignment screens.

## Internal Portal UX

Internal navigation is permission-driven:

```text
Dashboard
Customers
Services
Contracts
SLA
Tickets
Users
Roles
Audit
```

A role only sees modules for which it has permissions. Direct URL access is still denied server-side.

## Security Rules

1. Passwords are stored only as password hashes.
2. Login regenerates the session ID.
3. Inactive users cannot authenticate.
4. Customer users are isolated by `customer_id`.
5. Portal type is enforced server-side.
6. Permission checks are enforced server-side.
7. Scope checks are enforced server-side.
8. CSRF protection is required for state-changing forms.
9. User/role/scope mutations are audited.
10. Deleting referenced users/roles should be prevented or replaced with deactivation.
11. The system fails closed when authorization context is missing.

## Product Decisions Still Open

These remain configurable and are not hard-coded as permanent BOD decisions:

1. Whether one user may have multiple roles.
2. Whether one user may belong to multiple Customers.
3. Whether IT Owner scope is Customer-based, Service-based, or both.
4. Whether Customer Admin may create other Customer Users.
5. Whether Sales may view contracts but not ticket content.
6. Whether permissions are assigned only through roles or direct user grants are allowed.

The implementation should keep these decisions changeable without redesigning the whole database.

## Acceptance Criteria

### AC-RBAC-001
Valid internal user can authenticate.

### AC-RBAC-002
Inactive user cannot authenticate.

### AC-RBAC-003
Customer user is restricted to Customer Portal.

### AC-RBAC-004
Customer user cannot access another Customer's records.

### AC-RBAC-005
Permission check denies a missing permission.

### AC-RBAC-006
Scope check denies a record outside the user's scope.

### AC-RBAC-007
Internal user can access only modules granted by permission.

### AC-RBAC-008
Direct URL access cannot bypass authorization.

### AC-RBAC-009
State-changing operations require CSRF protection.

### AC-RBAC-010
User/role changes are auditable.

### AC-RBAC-011
Password is never stored or logged as plaintext.

### AC-RBAC-012
Regression tests for Customer, Service, Contract and SLA remain green.

## Test Gate

Module 05 cannot be marked `DONE` until:

- Documentation exists.
- PHP lint PASS.
- RBAC unit tests PASS.
- Portal isolation tests PASS.
- Scope tests PASS.
- Permission tests PASS.
- Security tests PASS.
- Regression Customer PASS.
- Regression Service PASS.
- Regression Contract PASS.
- Regression SLA PASS.
- GitHub Actions GREEN.

Only then proceed to Module 06 — Ticket / Request Management.

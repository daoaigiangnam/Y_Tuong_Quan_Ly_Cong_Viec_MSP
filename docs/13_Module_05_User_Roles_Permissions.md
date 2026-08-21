# Module 05 — User Roles & Permissions

## 1. Purpose

Module 05 defines authentication-adjacent authorization rules for the MSP/ITSM platform. Its purpose is to ensure that a user can perform an action only when the user is active, the requested portal is allowed, the required permission exists, and the target object is inside the user's data scope.

This module is a security boundary shared by Customer, Service, Ticket, Contract, SLA, Problem, Change, Task and CMDB workflows.

## 2. Authorization model

The authorization decision is evaluated using four dimensions:

1. **User status** — inactive users are denied.
2. **Portal type** — `INTERNAL` and `CUSTOMER` access are isolated.
3. **Permission** — the requested permission must be assigned to the user/role.
4. **Object scope** — customer-scoped users may access only objects belonging to their customer; global scope may access objects across customers.

A successful authorization returns `ALLOW`.

Expected denial reasons include:

- `DENY_INACTIVE`
- `DENY_PORTAL`
- `DENY_NO_PERMISSION`
- `DENY_SCOPE`

## 3. Core data concepts

### User

Representative authorization attributes:

- `id`
- `is_active`
- `portal_type`
- `scope_type`
- `customer_id`

Supported portal values:

- `INTERNAL`
- `CUSTOMER`

Supported scope concept:

- `GLOBAL`
- `CUSTOMER`

### Permission

Permissions use a stable resource/action naming convention, for example:

- `customer.view`
- `ticket.view`
- `ticket.update`
- `service.view`

The authorization layer must not infer permission from UI visibility alone; the server-side policy remains authoritative.

## 4. Authorization flow

```text
Request
  ↓
Is user active?
  ├─ No → DENY_INACTIVE
  └─ Yes
       ↓
Is portal allowed for requested context?
  ├─ No → DENY_PORTAL
  └─ Yes
       ↓
Does user have required permission?
  ├─ No → DENY_NO_PERMISSION
  └─ Yes
       ↓
Does object belong to allowed scope?
  ├─ No → DENY_SCOPE
  └─ Yes → ALLOW
```

## 5. Cross-module rules

RBAC must be enforced consistently at service/policy boundaries, not only in individual screens.

Examples:

- A customer user may view objects for its own customer but must not access another customer's records.
- A user without `ticket.update` may not update a ticket even if the UI route is directly invoked.
- A customer portal user must not execute an internal-only route.
- An inactive user must be denied regardless of previously assigned permissions.
- A global internal scope may access customer-scoped objects when the permission and portal context are valid.

## 6. Security requirements

- Deny by default when a required authorization attribute is missing or invalid.
- Never rely on client-side controls as the security boundary.
- Keep portal isolation and customer object scope server-side.
- Return deterministic denial codes so tests and API consumers can distinguish authorization failures.
- Apply the same policy semantics across all modules.

## 7. Executable acceptance criteria

The module is considered covered when the automated suite verifies at least:

- active user detection;
- inactive-user denial;
- permission-present and permission-missing cases;
- customer/internal portal isolation;
- matching and non-matching customer scope;
- global scope behavior;
- successful internal authorization;
- `DENY_NO_PERMISSION`;
- `DENY_SCOPE`;
- `DENY_PORTAL`;
- `DENY_INACTIVE`.

Primary executable coverage:

- `tests/rbac_validation_test.php`
- `tests/security_rbac_cross_module_test.php`
- `tests/security_route_guard_test.php`
- `tests/security_input_hardening_test.php`

## 8. Release traceability

```text
Module 05 specification
        ↓
app/rbac_policy.php
        ↓
tests/rbac_validation_test.php
        ↓
security regression tests
        ↓
Release Readiness traceability gate
```

A release must not be considered traceability-complete if this specification or its executable security coverage is missing.

## 9. Out of scope for this baseline

The engineering baseline does not yet claim completion of:

- production SSO/identity-provider integration;
- MFA implementation;
- full administration UI for role/permission management;
- production audit/SIEM integration;
- final customer-facing portal UX.

Those are product/deployment concerns and must be completed before production release.

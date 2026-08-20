# 12 — Module 04: SLA / Policy Engine

## Status

`IN DEVELOPMENT`

## Purpose

SLA Policy Engine là lớp nghiệp vụ xác định thời gian phản hồi và xử lý cam kết cho Ticket dựa trên Customer, Contract, Service, Priority và hiệu lực của Policy.

Module này **không tạo Ticket**. Nó cung cấp một cơ chế resolve SLA độc lập để Module Ticket sử dụng sau này.

## Design Principles

1. Không hard-code SLA vào Ticket.
2. Hỗ trợ nhiều Policy.
3. Hỗ trợ effective date/version.
4. Có thể cấu hình theo Contract + Service.
5. Không làm thay đổi lịch sử SLA của Ticket đã được resolve.
6. Business calendar được tách khỏi target duration.
7. Backend là nguồn quyết định cuối cùng; UI chỉ hiển thị/cấu hình.

## SLA Resolution Context

```text
Customer
   + Contract
   + Service
   + Priority
   + Created At
        ↓
SLA Resolver
        ↓
Policy Version
        ↓
Response Target
Resolution Target
Calendar
Escalation Policy
```

## Initial Policy Scope

Architecture supports:

- Global/default policy.
- Service policy.
- Contract + Service policy.
- Contract-specific override.

Exact precedence remains a Product Decision and must not be silently assumed as final business policy.

Recommended deterministic precedence for MVP:

```text
Contract + Service
      ↓
Service
      ↓
Global Default
```

Priority is evaluated inside the selected Policy.

## SLA Policy Fields

| Field | Required | Rule |
|---|---:|---|
| policy_code | Yes | Unique |
| policy_name | Yes | 2–200 chars |
| scope_type | Yes | GLOBAL / SERVICE / CONTRACT_SERVICE |
| service_id | Conditional | Required for SERVICE / CONTRACT_SERVICE |
| contract_id | Conditional | Required for CONTRACT_SERVICE |
| timezone | Yes | Valid IANA timezone or configured default |
| calendar_id | Yes | Business calendar reference |
| effective_from | Yes | Date/time |
| effective_to | No | Must be after effective_from |
| status | Yes | DRAFT / ACTIVE / INACTIVE / EXPIRED |
| version | System | Monotonic per policy identity |
| notes | No | Internal |

## SLA Priority Rule

Each Policy contains priority targets. Initial supported priority codes:

- P1
- P2
- P3
- P4

Targets are expressed in minutes for deterministic calculation.

Example:

```text
P1 → response 15m / resolution 120m
P2 → response 30m / resolution 240m
P3 → response 120m / resolution 480m
P4 → response 240m / resolution 960m
```

These are sample values only and are not BOD-approved defaults unless explicitly configured.

## Business Calendar

Calendar defines when SLA time is counted.

Initial model:

```text
Calendar
 ├── timezone
 ├── Monday hours
 ├── Tuesday hours
 ├── Wednesday hours
 ├── Thursday hours
 ├── Friday hours
 ├── Saturday hours
 ├── Sunday hours
 └── Holidays
```

The calculation engine must support:

- 24x7.
- Business hours.
- Multiple intervals per day.
- Holidays.

## SLA Versioning

Example:

```text
Network Standard v1
Effective 2026-01-01
P1 = 15m / 120m

Network Standard v2
Effective 2027-01-01
P1 = 10m / 60m
```

A Ticket must store the resolved Policy identity/version when SLA is assigned. Future Policy changes must not retroactively rewrite historical SLA targets.

## SLA Instance

When a Ticket consumes SLA, the system should persist an instance containing at minimum:

- ticket_id
- policy_id
- policy_version
- priority
- response_target_at
- resolution_target_at
- calendar_id/version
- resolved_at
- breach state

The actual Ticket module will own the final SLA instance relation; Module 04 only defines the resolution contract and calculation rules.

## Screens

### SLA-01 Policy List

Columns:

`Code | Name | Scope | Version | Effective From | Effective To | Status | Updated | Actions`

Filters:

- Status.
- Scope.
- Service.
- Contract.
- Effective date.

### SLA-02 Policy Detail

Tabs:

1. Overview.
2. Priority Targets.
3. Calendar.
4. Scope.
5. Versions.
6. Audit.

### SLA-03 Calendar Management

Display weekly schedule and holiday exceptions.

## Validation Rules

1. Policy code is unique.
2. Contract scope requires a Contract.
3. Service scope requires a Service.
4. Contract + Service scope requires both.
5. Effective To must be after Effective From.
6. Active overlapping versions for the same policy identity must be rejected unless explicitly modeled as non-overlapping effective ranges.
7. Response target must be positive or explicitly configured as unlimited where Product allows it.
8. Resolution target must be positive or explicitly configured as unlimited where Product allows it.
9. Priority must be from configured priority catalog.
10. Calendar must exist and be active.

## Resolver Contract

Conceptual input:

```text
resolveSla(
    customerId,
    contractId,
    serviceId,
    priority,
    occurredAt
)
```

Output:

```text
{
  policy_id,
  policy_version,
  response_minutes,
  resolution_minutes,
  calendar_id,
  timezone,
  effective_from,
  effective_to
}
```

The implementation must return a deterministic result or a clear `NO_MATCHING_POLICY` error. It must never silently select an arbitrary policy.

## Security

- Internal authorized users only for policy administration.
- Customer Portal users cannot modify SLA Policy.
- Permission checks occur server-side.
- All policy mutations are audited.
- Historical policy records must not be deleted if referenced by Tickets.

## Acceptance Criteria

### AC-SLA-001
Authorized user can create a valid Global SLA Policy.

### AC-SLA-002
Duplicate policy code is rejected.

### AC-SLA-003
Service-scoped policy requires a valid Service.

### AC-SLA-004
Contract + Service policy requires both Contract and Service.

### AC-SLA-005
Invalid effective date ranges are rejected.

### AC-SLA-006
Priority targets reject zero/negative duration unless an explicit unlimited policy exists.

### AC-SLA-007
Resolver selects the deterministic highest applicable scope according to the documented precedence.

### AC-SLA-008
Resolver selects the correct effective version for the supplied timestamp.

### AC-SLA-009
A future Policy Version does not change the result for a historical timestamp.

### AC-SLA-010
Inactive/expired policies are not selected.

### AC-SLA-011
Unauthorized users cannot create/update/delete SLA policies.

### AC-SLA-012
Policy mutations create audit records.

## Test Gate

Module cannot be marked `DONE` until:

- PHP lint PASS.
- Migration PASS.
- Validation tests PASS.
- Resolver tests PASS.
- Effective-date/version tests PASS.
- Scope precedence tests PASS.
- Permission tests PASS.
- Audit tests PASS.
- Regression Customer PASS.
- Regression Service PASS.
- Regression Contract PASS.
- GitHub Actions GREEN.

Only then proceed to Module 05 — User / Role / Permission / Portal Access.

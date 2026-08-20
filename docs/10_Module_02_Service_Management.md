# 10 — Module 02: Service Management

## Status

`IN DEVELOPMENT`

## Objective

Quản lý danh mục dịch vụ mà công ty cung cấp cho Customer, làm nguồn tham chiếu chuẩn cho Contract, Ticket, SLA và Assignment.

## Design Principle

Service là khái niệm nghiệp vụ **không đồng nghĩa với IT**. Một Service có thể thuộc Network, Security, Application, Facility, HR Outsourcing hoặc bất kỳ nhóm dịch vụ nào được Product cấu hình.

## Current Data Model

Repository đã có bảng `services` với `id, code, name, description, is_active`. Module 02 dùng bảng này trước. Service Category, Assignment Group và SLA Mapping sẽ được bổ sung ở policy/module tương ứng.

## Screens

### SERV-01 Service List

Columns: `Code | Service | Description | Active Contracts | Open Tickets | Status | Actions`

Filters: code/name, status, active contracts, open tickets.

### SERV-02 Service Form

- Service Code — required, unique, immutable after creation.
- Service Name — required, 2–150 chars.
- Description — optional.
- Status — ACTIVE/INACTIVE.

### SERV-03 Service Detail

Overview, Contracts using Service, Tickets using Service, future SLA Policy, future Assignment Group, Audit.

## Business Rules

1. Service Code must be unique.
2. Service Code cannot be changed after creation.
3. Inactive Service cannot be selected for new Contract Service mapping unless an explicit policy allows it.
4. Existing Contract/Ticket history remains readable after Service becomes inactive.
5. No physical delete from UI for a Service referenced by Contract/Ticket.
6. Service is generic; do not hard-code department names.
7. Mutations require CSRF and authorization.
8. Mutations generate audit records.

## Relationship

```text
Customer
   │
   └── Contract
          │
          └── Contract Service ─── Service
                                      │
                                      └── Ticket
```

A Service may be used by many Contracts. A Contract may contain many Services through `contract_services`.

## Acceptance Criteria

- AC-SERV-001: Authorized internal user can create a Service with a unique Code.
- AC-SERV-002: Duplicate Service Code is rejected without partial data.
- AC-SERV-003: Service Code is read-only during edit.
- AC-SERV-004: Authorized user can activate/deactivate a Service.
- AC-SERV-005: Inactive Service remains visible in historical Contract/Ticket records.
- AC-SERV-006: Service list shows active contract and open ticket counts.
- AC-SERV-007: Customer Portal users cannot access internal Service Management.
- AC-SERV-008: All create/update/status changes generate audit records.

## Test Gate

PHP lint, validation, duplicate-code, status, historical-reference, authorization and audit tests must pass. GitHub Actions must be GREEN before Module 02 is marked DONE.

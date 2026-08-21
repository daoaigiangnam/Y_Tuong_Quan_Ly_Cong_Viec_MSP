# UAT Readiness & Sign-off

## 1. Purpose

This document defines the gate between automated engineering regression and real-user User Acceptance Testing (UAT).

A green CI pipeline means the release is technically consistent. It does **not** mean business acceptance has been completed.

## 2. Entry Criteria

UAT may start only when:

- PHP syntax checks pass.
- Validation regression tests pass.
- Security/RBAC regression passes.
- Platform end-to-end, rollback, and negative-path tests pass.
- Release traceability is complete.
- Deployment hardening checks pass.
- UAT test data and user accounts are prepared.
- A business owner is assigned for final acceptance.

## 3. UAT Scenario Matrix

| ID | Business scenario | Expected result | Evidence |
|---|---|---|---|
| UAT-01 | Login and role-based access | User reaches only authorized functions | Screenshot + user/role |
| UAT-02 | Customer lifecycle | Customer can be created, viewed, updated and isolated by tenant/customer boundary | Record ID + screenshot |
| UAT-03 | Service catalog/service management | Service data follows configured lifecycle and ownership | Record ID |
| UAT-04 | Contract lifecycle | Contract creation, status and alert behavior follow policy | Contract ID + screenshot |
| UAT-05 | SLA policy | SLA target/priority behavior is applied consistently | Ticket/SLA evidence |
| UAT-06 | Ticket/request lifecycle | Request can be created, assigned, updated and closed with traceability | Ticket ID |
| UAT-07 | Task management | Task ownership, state transitions and permissions work as designed | Task ID |
| UAT-08 | Problem management | Problem record links investigation/root-cause information correctly | Problem ID |
| UAT-09 | Change management | Change request follows approval/state controls | Change ID |
| UAT-10 | Knowledge management | Knowledge article can be created, governed and consumed by intended users | Article ID |
| UAT-11 | CMDB | CI information and relationships remain traceable | CI ID + relationship evidence |
| UAT-12 | Audit/traceability | Important business actions retain enough evidence for operational review | Audit evidence |
| UAT-13 | Negative authorization | Unauthorized user/action is rejected without data leakage | HTTP response/screenshot |
| UAT-14 | Rollback/recovery | Failed operation does not leave an invalid business state | Before/after evidence |

## 4. UAT Execution Rules

1. Use a dedicated UAT database or disposable environment.
2. Use representative but non-production-sensitive data.
3. Execute each scenario with the intended user role.
4. Record actual result and evidence for every scenario.
5. Any failed scenario becomes a defect or an explicit accepted deviation.
6. Do not mark the release accepted solely because CI is green.

## 5. Severity

- **S1 Critical:** security, authorization, data corruption, or complete business-blocking failure. UAT cannot pass.
- **S2 High:** major workflow unavailable or materially incorrect. Requires correction or explicit business waiver.
- **S3 Medium:** workaround exists and core business flow remains usable.
- **S4 Low:** cosmetic/documentation/minor usability issue.

## 6. Sign-off

### Technical acceptance

- [ ] CI regression green
- [ ] Security regression green
- [ ] Platform integration green
- [ ] Deployment hardening green
- [ ] Release traceability green

### Business acceptance

- [ ] UAT scenarios executed
- [ ] S1/S2 defects resolved or formally waived
- [ ] Evidence attached
- [ ] Business Owner approval recorded

### Release decision

- **GO:** acceptance criteria satisfied and no blocking defect remains.
- **CONDITIONAL GO:** only documented S3/S4 issues remain, with owner and due date.
- **NO-GO:** any unresolved S1/S2 issue or missing mandatory acceptance evidence.

## 7. Important Boundary

Automated UAT-readiness checks validate that the project is **ready to enter UAT**. They do not simulate human business judgment. Final UAT approval remains a business-owner decision.

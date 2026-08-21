# 21 — Release Readiness Audit

## Purpose

This document is the final traceability checkpoint for the current `feature/php-mysql-msp-platform` implementation track.

The repository has reached a point where the CI suite covers database integrity, module validation, security hardening, platform integration, deployment hardening and release regression. The remaining question is not simply **"do tests pass?"**, but **"can we prove that the documented modules are represented by code and executable tests without silently missing a module or artifact?"**

## Current release posture

**Status: RELEASE-CANDIDATE — ENGINEERING BASELINE**

This does **not** mean production-ready. The repository currently contains a working PHP/MySQL skeleton plus policy/services/tests and CI gates. Production readiness still requires real UI completion, deployment configuration, operational integrations and user acceptance testing.

## Module traceability matrix

| Logical Module | Primary specification | Required executable coverage | Current evidence |
|---|---|---|---|
| M01 Customer Management | `docs/09_Module_01_Customer_Management.md` | customer validation | Present |
| M02 Service Management | `docs/10_Module_02_Service_Management.md` | service validation | Present |
| M03 Contract Management | `docs/11_Module_03_Contract_Management.md` | contract validation + service/MySQL coverage | Present |
| M04 SLA Policy Engine | `docs/12_Module_04_SLA_Policy_Engine.md` | SLA validation | Present |
| M05 User Roles & Permissions | `docs/13_Module_05_User_Roles_Permissions.md` | RBAC validation + security regression | Present |
| M06 Ticket Request Management | `docs/14_Module_06_Ticket_Request_Management.md` | ticket validation + service/MySQL coverage | Present |
| M07 Contract Alert Engine | `docs/15_Module_07_Contract_Management.md` | contract alert/MySQL coverage | Present |
| M08 Problem Management | `docs/16_Module_08_Problem_Management.md` | problem validation + service/MySQL coverage | Present |
| M09 Change Management | `docs/17_Module_09_Change_Management.md` | change validation + service/MySQL coverage | Present |
| M09-T Task Management | `docs/18_Module_09_Task_Management.md` | task validation + service/MySQL + UI smoke | Present |
| M10 Knowledge Management | `docs/18_Module_10_Knowledge_Management.md` | knowledge validation + service/MySQL coverage | Present |
| M11 CMDB Management | `docs/19_Module_11_CMDB_Management.md` | CMDB validation + service/MySQL coverage | Present |

### Numbering decision

The existing document names contain a historical numbering collision: Task Management is named `Module_09`, while Knowledge and CMDB are `Module_10` and `Module_11`. This audit treats **Task Management as M09-T** so that the repository history is preserved without pretending the filename has already been renumbered.

A future documentation cleanup may rename the Task specification, but that rename is deliberately outside the current release gate because it has no runtime impact.

## CI / quality gates already established

- PHP syntax/lint gate.
- Clean database schema installation.
- Migration coverage.
- Seed/reference data coverage.
- Module validation tests.
- MySQL service/integration tests.
- Cross-module referential integrity tests.
- RBAC isolation tests.
- Route-guard regression tests.
- Input-hardening/security tests.
- Platform end-to-end integration test.
- Platform rollback/cleanup test.
- Platform negative-path validation test.
- Deployment hardening regression gate.
- Release traceability gate.
- Release regression gate.

## Deployment hardening gate

The engineering baseline now has an executable deployment hardening checkpoint in `tests/deployment_hardening_test.php` with its operational runbook in `docs/22_Deployment_Hardening.md`.

The gate verifies, at source/repository level:

- dedicated `public/` web-root boundary;
- installer isolation from the public directory;
- environment-driven database and mail configuration;
- `.env` protection through Git ignore rules;
- upload storage isolation;
- session-cookie hardening;
- explicit production controls for installer removal/disablement, secrets, backups, logging and rollback.

This is a **deployment-design gate**, not a substitute for infrastructure penetration testing, backup restore testing or production UAT.

## Remaining release blockers

These are **product-delivery** items rather than another round of synthetic CI tests:

1. Complete the actual Service Portal UI and role-specific navigation.
2. Complete Customer + Contact CRUD and object-scope enforcement in the real UI/API path.
3. Complete Service Catalog + SLA mapping screens.
4. Complete Contract CRUD + renewal workflow in the real UI.
5. Complete Ticket/SLA/assignment/escalation UI flows.
6. Complete SMTP provider/queue configuration and operational monitoring.
7. Complete dashboards/reporting required by BOD/operations.
8. Execute UAT with representative customer/internal roles.
9. Perform deployment hardening in the target infrastructure: secrets, web root, `install.php` removal/disablement, backups, logging and rollback procedure.
10. Keep Bitrix24 integration explicitly optional until the core ITSM system is accepted.

## Release decision rule

The branch may be considered **engineering baseline complete** when the automated gates are green and this audit remains green.

The system may be considered **production-ready** only after the product-delivery blockers above are accepted by BOD/Product/Operations.

## Audit invariant

Any new module must add, at minimum:

```text
Specification → Policy/Service implementation → Validation test → MySQL/integration test where applicable → CI workflow → Release regression coverage
```

If any link is missing, the module is **not** considered release-ready merely because the existing global regression suite passes.

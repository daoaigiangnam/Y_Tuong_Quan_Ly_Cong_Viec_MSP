# Customer ITSM / MSP Management Platform

> Nền tảng Customer ITSM/MSP bằng **PHP thuần + MySQL + Bootstrap**, phát triển từ nghiệp vụ gốc và các tài liệu kiến trúc trong repository.

## Source of truth

`feature/php-mysql-msp-platform` là **implementation branch của nghiệp vụ mới nhất**.

`main` giữ nghiệp vụ gốc, ý tưởng và tài liệu kiến trúc; không được xem là source code implementation để đồng bộ ngược vào feature branch.

## Mục tiêu nghiệp vụ

```text
Customer
  ↓
Contact
  ↓
Contract
  ↓
Service / SLA
  ↓
Ticket
  ├── Task
  ├── Problem
  │     └── Change
  └── Knowledge

CMDB / CI ───────────────┘
Audit / Email / History xuyên suốt
```

## Portal Architecture

Một Shared Design System với hai experience:

- **Service Portal**: IT Owner, IT Support, IT Lead và các team vận hành theo RBAC/scope.
- **Customer Portal**: trải nghiệm riêng cho khách hàng.
- Hai portal dùng chung Ticket domain và cùng một MySQL source of truth.
- Navigation, field visibility, action và dashboard được quyết định bởi permission + portal + object scope.
- Bitrix24 REST là integration option cho giai đoạn sau, không phải dependency của core product.

## Implementation hiện tại

### Core platform

- Login/logout, session, password hashing
- CSRF protection
- PDO + prepared statements
- Database-backed RBAC permissions
- Portal separation: SERVICE / CUSTOMER
- Object scope: GLOBAL / CUSTOMER / SERVICE / ASSIGNED
- Permission guard `require_permission()`
- Audit log
- Email log
- Customer và Customer Contacts

### Contract

- Contract CRUD service
- Contract lifecycle
- Contract → Service relationship
- Contract alert rules 90/60/30 mặc định
- Contract alert execution/history
- Contract UI database-backed

Contract type:

```text
FULL_PACKAGE
PAY_PER_INCIDENT
```

### Ticket / SLA

- Ticket create/list/detail
- Assignment
- Request type
- Requester
- Ticket lifecycle
- First response timestamp
- SLA snapshot / target response / target resolve
- Customer-visible vs internal comments
- Timeline/history
- Customer Confirm / Reopen
- Reopen counter
- Email alert

Ticket lifecycle:

```text
NEW → TRIAGED → ASSIGNED → IN_PROGRESS
                         ↓
        PENDING_CUSTOMER / PENDING_VENDOR / PENDING_INTERNAL
                         ↓
                      RESOLVED
                         ↓
                       CLOSED

RESOLVED/CLOSED → REOPENED
```

### Problem

- Problem creation
- Lifecycle policy
- Ticket linking
- Analysis
- Documents
- History

### Change

- Change creation
- Lifecycle
- Approval
- Implementation plan
- Rollback plan
- Test plan
- Success criteria
- Ticket / Problem linking
- History

### Knowledge

- Article creation
- Draft / review / publish lifecycle
- Versioning
- Content update
- Visibility
- Ticket / Problem / Change linking
- History

### CMDB

- CI creation
- CI lifecycle
- CI relationships
- CI audit
- Customer/service association
- Criticality/environment metadata

### Task

- Task creation
- Assignment
- Lifecycle
- Ticket linkage
- History

## RBAC model

Permission is evaluated in this order:

```text
Active user
   ↓
Portal
   ↓
Permission
   ↓
Object scope
   ↓
ALLOW / DENY
```

Available portal types:

```text
SERVICE
CUSTOMER
```

Available scopes:

```text
GLOBAL
CUSTOMER
SERVICE
ASSIGNED
```

Permissions are stored in `permissions` and assigned to roles through `role_permissions`; they are loaded into the authenticated session at login.

## Stack

- PHP 8.3+ — plain PHP, no Laravel/framework
- MySQL 5.7+ / MariaDB compatible
- PDO + prepared statements
- Bootstrap 5.3
- Server-rendered UI
- JavaScript tối thiểu
- Cron cho Contract Alert

## Database

`database/schema.sql` là schema baseline của implementation branch.

Migrations được áp dụng theo thứ tự trong `database/migrations/` để nâng cấp database hiện hữu.

Migration groups hiện có:

```text
002 Customer/User
003 Ticket request/SLA hardening
004 Contract Alert Engine
005 Problem
006 Change
007 Knowledge
008 CMDB
009 Task
010 RBAC / Portal / Scope / Permissions
```

## Cấu trúc chính

```text
app/
  auth.php
  bootstrap.php
  config.php
  db.php
  helpers.php
  rbac_policy.php
  *_policy.php
  services/

public/
  index.php
  contract.php
  ...

cron/
  contract_alerts.php

database/
  schema.sql
  seed.sql
  migrations/

docs/
tests/
storage/
```

## Cài đặt

### 1. Yêu cầu

- PHP 8.3+
- PDO MySQL
- MySQL 5.7+ hoặc MariaDB
- Apache/Nginx
- PHP CLI

### 2. Database

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p msp_itsm < database/seed.sql
```

Nếu nâng cấp database hiện hữu, chạy các migration còn thiếu theo thứ tự, bao gồm `010_rbac_scope_permissions.sql`.

### 3. Environment

```text
MSP_DB_HOST=127.0.0.1
MSP_DB_PORT=3306
MSP_DB_NAME=msp_itsm
MSP_DB_USER=root
MSP_DB_PASS=your_password
MSP_MAIL_FROM=itsm@example.com
MSP_MAIL_FROM_NAME=MSP ITSM
```

### 4. Web root

Khuyến nghị DocumentRoot trỏ vào `public/`.

### 5. Install

Chạy `install.php` một lần nếu môi trường cần seed user ban đầu. Installer phải được disable/xóa trước production.

## Development rules

Mỗi module phải hoàn thành theo chu trình:

```text
Business Analysis
    ↓
Database / Migration
    ↓
Policy
    ↓
Service
    ↓
UI / Portal
    ↓
PHP Lint
    ↓
Functional / Business Tests
    ↓
Security / RBAC Tests
    ↓
Integration / E2E
    ↓
GitHub Actions PASS
    ↓
Commit
    ↓
Module DONE
```

Không dùng `main` để đánh giá feature branch về mặt implementation. Feature branch phải tự đủ và nhất quán với nghiệp vụ mới nhất của nó.

## Release gates

```text
Module completeness
        ↓
PHP Lint + Unit/Business Tests
        ↓
Database Integration
        ↓
Security / RBAC / Scope
        ↓
Cross-module E2E
        ↓
Negative-path / Rollback
        ↓
Regression
        ↓
UAT Readiness
        ↓
Production Readiness
```

Production readiness không đồng nghĩa đã go-live. Trước production thật phải có backup/restore evidence, RPO/RTO, monitoring, release approval và rollback evidence.

## Stabilization / implementation status

- [x] Merge conflicts resolved
- [x] Ticket/SLA schema baseline
- [x] ContractService transaction/lifecycle implementation
- [x] Ticket/Problem/Change/Knowledge/CMDB/Task services present
- [x] Contract UI database-backed
- [x] Contract type aligned with database enum
- [x] Ticket status badges aligned with current lifecycle
- [x] Database-backed RBAC permission model
- [x] Portal and object-scope policy
- [x] Authentication loads role permissions
- [x] RBAC policy regression tests
- [ ] Full PHP lint on target environment
- [ ] Full MySQL integration test
- [ ] Full RBAC/object-scope regression against live database
- [ ] Cross-module E2E regression
- [ ] CI green on the complete stabilization sequence

## Lộ trình tiếp theo

1. Service Portal shell + navigation driven by permission.
2. Customer Portal shell with customer scope enforced at route/service level.
3. Customer + Contacts CRUD.
4. Service Catalog + SLA mapping.
5. Contract CRUD/list/detail/renewal workflow.
6. Ticket + SLA + assignment + escalation.
7. Customer Portal Ticket experience.
8. Email template/configuration + SMTP provider/queue.
9. Dashboards và reporting theo role/scope.
10. Problem / Change / Knowledge / CMDB / Task UI flows.
11. Automated tests, CI/CD và security hardening.
12. Bitrix24 REST integration — future / optional.

# Customer ITSM / MSP Management Platform

> Nền tảng Customer ITSM/MSP bằng **PHP thuần + MySQL + Bootstrap**, phát triển từ nghiệp vụ gốc và các tài liệu kiến trúc trong repository.

## Source of truth

`feature/php-mysql-msp-platform` là **implementation branch của nghiệp vụ mới nhất**.

`main` giữ nghiệp vụ gốc, ý tưởng và tài liệu kiến trúc; không được xem là source code implementation để đồng bộ ngược vào feature branch.

## Mục tiêu nghiệp vụ

Nền tảng quản lý xuyên suốt:

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

- **Service Portal**: IT Owner, IT Support, IT Lead và các team vận hành khác theo RBAC/scope.
- **Customer Portal**: giao diện riêng cho khách hàng.
- Hai portal dùng chung Ticket domain và cùng một MySQL source of truth.
- Navigation, field visibility, action và dashboard thay đổi theo role/scope.
- Bitrix24 REST là integration option cho giai đoạn sau, không phải dependency của core product.

## Implementation hiện tại

### Core platform

- Login/logout, session, password hashing
- CSRF protection
- PDO + prepared statements
- RBAC nền tảng
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

Contract type là:

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

Ticket lifecycle hỗ trợ:

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

Các nhóm migration hiện có gồm Customer/User, Ticket hardening, Contract Alert, Problem, Change, Knowledge, CMDB và Task.

## Cấu trúc chính

```text
app/
  auth.php
  bootstrap.php
  config.php
  db.php
  helpers.php
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

Nếu nâng cấp database hiện hữu, chạy các migration còn thiếu theo thứ tự.

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

Chạy `install.php` một lần nếu môi trường cần seed user ban đầu. Đổi password và xóa `install.php` trước production.

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

## Current stabilization status

Trước khi phát triển nghiệp vụ mới, branch phải đạt các điều kiện:

- [x] Merge conflicts resolved
- [x] `schema.sql` aligned with current Ticket/SLA model
- [x] ContractService transaction/lifecycle implementation
- [x] Ticket/Problem/Change/Knowledge/CMDB/Task services present
- [x] Contract UI uses real database and ContractService
- [x] Contract type aligned with database enum
- [x] Ticket status badges aligned with current lifecycle
- [ ] Full PHP lint on the target environment
- [ ] Full database integration test
- [ ] Full RBAC/object-scope regression
- [ ] Cross-module E2E regression
- [ ] CI green on the stabilization commit

## Lộ trình sau stabilization

1. Hoàn thiện Service Portal shell + Customer Portal shell.
2. Hoàn thiện RBAC + object-level/customer scope + field visibility.
3. Hoàn thiện Customer + Contacts CRUD.
4. Hoàn thiện Service Catalog + SLA mapping.
5. Hoàn thiện Contract CRUD/list/detail/renewal workflow.
6. Hoàn thiện Ticket + SLA + assignment + escalation.
7. Hoàn thiện Customer Portal Ticket experience.
8. Email template/configuration + SMTP provider/queue.
9. Dashboards và reporting theo role.
10. Hoàn thiện Problem / Change / Knowledge / CMDB / Task UI flows.
11. Automated tests, CI/CD và security hardening.
12. Bitrix24 REST integration — future / optional.

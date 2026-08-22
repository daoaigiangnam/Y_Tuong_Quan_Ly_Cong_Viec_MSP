# Customer ITSM / MSP Management Platform

> Nền tảng Customer ITSM/MSP bằng **PHP thuần + MySQL + Bootstrap**, thiết kế theo tài liệu nghiệp vụ BOD/Dev trong repo.

## Mục tiêu

Hệ thống quản lý xuyên suốt: Customer → Contract → Service → Ticket → SLA → Service Owner → Service Agent → Customer Confirmation/Reopen → Escalation.

## Kiến trúc Portal đã chốt

Sản phẩm có **một Shared Design System nhưng hai experience**:

- **Service Portal**: portal dùng chung cho toàn bộ team Công ty Dịch vụ có quyền vận hành dịch vụ — IT Owner, IT Support, IT Lead và các team/role khác như Network, Security, Application, Onsite, Sales, Contract Admin... tùy phân quyền.
- **Customer Portal**: giao diện riêng cho khách hàng.
- Hai portal dùng chung Ticket domain/service và cùng một MySQL source of truth.
- UI component, status, timeline, table, form và visual language thống nhất; navigation, field visibility, action và dashboard thay đổi theo role/scope.
- Bitrix24 REST **để mở cho giai đoạn sau**, không phải dependency của core product.

Chi tiết: [06 — Portal Architecture: Shared UI/UX](docs/06_Portal_Architecture_Shared_UIUX.md)

## Tài liệu nghiệp vụ

- [01 — BOD / Business Case](docs/01_BOD_Business_Case.md)
- [02 — Dev / Product / UIUX Specification](docs/02_DEV_UIUX_Specification.md)
- [03 — Data Model & Technical Blueprint](docs/03_Data_Model_Technical_Blueprint.md)
- [04 — Traceability & Acceptance Criteria](docs/04_Traceability_Acceptance_Criteria.md)
- [05 — Open Questions & Implementation Phases](docs/05_Open_Questions_Phases.md)
- [06 — Portal Architecture: Shared UI/UX](docs/06_Portal_Architecture_Shared_UIUX.md)

## Code hiện tại

### Stack

- PHP 8.3+ (plain PHP, no Laravel/framework)
- MySQL 5.7+ / MariaDB tương thích
- PDO + prepared statements
- Bootstrap 5.3
- Bootstrap Icons
- JavaScript tối thiểu, server-rendered UI
- Cron cho Contract Alert

### Chức năng đã có trong skeleton chạy được

- Login/logout, session, password hashing
- RBAC nền tảng
- Customer Portal dashboard cơ bản
- Ticket create/list/detail
- Ticket assignment
- Ticket timeline/history
- Public/internal comments
- Ticket state transition
- Customer Confirm / Reopen
- Reopen counter + email alert
- Contract list/detail
- Contract Alert Rule #1/#2/#3
- Contract Alert History
- Cron worker cho cảnh báo hết hạn
- Audit log
- Email log
- CSRF protection
- PDO prepared statements

## Cấu trúc

```text
app/
  auth.php
  bootstrap.php
  config.php
  db.php
  helpers.php
  services/
    TicketService.php
    ContractAlertService.php
public/
  index.php
  assets/app.css
cron/
  contract_alerts.php
database/
  schema.sql
  seed.sql
  migrations/002_customer_user.sql
install.php
storage/uploads/
docs/
```

## Cài đặt

### 1. Yêu cầu

- PHP 8.3+
- PDO MySQL
- MySQL 5.7+ hoặc MariaDB
- Apache/Nginx
- PHP CLI để chạy cron

### 2. Database

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p msp_itsm < database/seed.sql
mysql -u root -p msp_itsm < database/migrations/002_customer_user.sql
```

### 3. Cấu hình

Có thể dùng environment variables:

```text
MSP_DB_HOST=127.0.0.1
MSP_DB_PORT=3306
MSP_DB_NAME=msp_itsm
MSP_DB_USER=root
MSP_DB_PASS=your_password
MSP_MAIL_FROM=itsm@example.com
MSP_MAIL_FROM_NAME=MSP ITSM
```

Hoặc sửa `app/config.php`.

### 4. Web root

Khuyến nghị đặt DocumentRoot vào `public/`.

Apache/Nginx cần cho phép PHP-FPM xử lý `public/index.php`.

### 5. Khởi tạo user

Mở `/install.php` một lần sau khi import DB. Mặc định tạo user demo theo seed/install script.

**Đổi password ngay và xóa `install.php` trước production.**

### 6. Contract Alert Cron

Chạy mỗi ngày, ví dụ 08:00:

```cron
0 8 * * * /usr/bin/php /path/to/project/cron/contract_alerts.php >> /var/log/msp-contract-alert.log 2>&1
```

## Kiến trúc dữ liệu

ITSM là System of Record. Customer Portal và Service Portal cùng làm việc trên một Ticket domain/database. Bitrix24 chỉ là **integration option** cho Internal Collaboration/Task Execution trong tương lai; không tạo hai Ticket độc lập sống song song.

## Quy tắc phát triển

Mỗi module phải hoàn thành theo chu trình:

```text
Analysis → Code → PHP Lint → Functional Test → GitHub Actions PASS → Commit → Module DONE
```

Không chuyển module tiếp theo nếu module hiện tại chưa PASS.

## Delivery / Release Gates

Các module và lớp kiểm soát đã được đưa vào CI theo từng gate:

```text
Module 01–11
   ↓
PHP Lint + Module Tests
   ↓
Security / RBAC
   ↓
Platform Integration E2E
   ↓
Rollback + Negative-path Validation
   ↓
Release Regression
   ↓
UAT Readiness + Sign-off
   ↓
Production Go-Live Readiness
   ↓
Backup / Restore / DR
```

Production readiness không đồng nghĩa hệ thống đã được triển khai production. Trước go-live thực tế vẫn phải có backup/restore evidence, RPO/RTO, release approval, monitoring và rollback evidence.

Runbook: [24 — Production Go-Live Readiness](docs/24_Production_GoLive_Readiness.md)

DR: [25 — Backup / Restore / DR Runbook](docs/25_Backup_Restore_DR_Runbook.md)

## Lộ trình tiếp theo

1. Hoàn thiện Service Portal shell + Customer Portal shell trên Shared Design System.
2. Hoàn thiện RBAC + object-level/customer scope + field visibility.
3. Hoàn thiện Customer + Contacts.
4. Hoàn thiện Service Catalog + SLA mapping.
5. Hoàn thiện Contract CRUD + renewal workflow.
6. Hoàn thiện Ticket + SLA + assignment + escalation trên Service Portal.
7. Hoàn thiện Customer Portal Ticket experience + Confirm/Reopen.
8. Email template/configuration + SMTP provider/queue.
9. Dashboards theo role và reporting.
10. Bitrix24 REST integration — **future / optional**.
11. Problem / Change / Knowledge Base / CMDB.
12. Automated tests, CI/CD, security hardening.

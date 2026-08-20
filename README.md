# Customer ITSM / MSP Management Platform

> Nền tảng Customer ITSM/MSP bằng **PHP thuần + MySQL + Bootstrap**, thiết kế theo tài liệu nghiệp vụ BOD/Dev trong repo.

## Mục tiêu

Hệ thống quản lý xuyên suốt: Customer → Contract → Service → Ticket → SLA → IT Owner → IT Support → Customer Confirmation/Reopen → Escalation.

## Tài liệu nghiệp vụ

- [01 — BOD / Business Case](docs/01_BOD_Business_Case.md)
- [02 — Dev / Product / UIUX Specification](docs/02_DEV_UIUX_Specification.md)
- [03 — Data Model & Technical Blueprint](docs/03_Data_Model_Technical_Blueprint.md)
- [04 — Traceability & Acceptance Criteria](docs/04_Traceability_Acceptance_Criteria.md)
- [05 — Open Questions & Implementation Phases](docs/05_Open_Questions_Phases.md)

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
- RBAC: Admin, Customer, IT Owner, IT Support, IT Lead, Sales
- Customer Portal dashboard cơ bản
- Ticket create/list/detail
- Ticket assignment
- Ticket timeline/history
- Public/internal comments
- Ticket state transition
- Customer Confirm / Reopen
- Reopen counter + email alert cho IT Owner/IT Lead/Support
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

Mở `/install.php` một lần sau khi import DB. Mặc định tạo:

```text
admin / ChangeMe123!
customer / Customer123!
```

**Đổi password ngay và xóa `install.php` trước production.**

### 6. Contract Alert Cron

Chạy mỗi ngày, ví dụ 08:00:

```cron
0 8 * * * /usr/bin/php /path/to/project/cron/contract_alerts.php >> /var/log/msp-contract-alert.log 2>&1
```

## Kiến trúc dữ liệu

ITSM là System of Record. Bitrix24 chỉ nên là lớp Internal Collaboration/Task Execution khi tích hợp API. Không tạo hai Ticket độc lập sống song song.

## Lộ trình tiếp theo

1. Hoàn thiện SLA engine và due_at tự động theo Contract/Service/Priority.
2. Attachment upload + antivirus/storage policy.
3. Contract CRUD + renewal workflow.
4. Email template/configuration + SMTP provider/queue.
5. Customer Portal UI hoàn chỉnh.
6. IT Owner / IT Support / IT Lead dashboards nâng cao.
7. Bitrix24 REST integration.
8. Problem / Change / Knowledge Base / CMDB.
9. Automated tests, CI/CD, security hardening.

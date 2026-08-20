# 05 — Open Questions & Implementation Phases

## 1. Những quyết định cần BOD/Product chốt

1. Contract type chính thức: Full Package / Per Incident / Hourly / Hybrid?
2. SLA theo Service hay theo Contract + Service?
3. Customer có được chọn Priority không?
4. Resolved → Closed có auto-close sau bao nhiêu ngày nếu Customer không phản hồi?
5. Alert schedule mặc định là 90/60/30 hay cấu hình theo Contract?
6. Email Customer có bắt buộc ở cả 3 mốc hay chỉ Internal?
7. Có cho phép nhiều IT Owner theo từng Service không?
8. Ticket có tạo Bitrix Task tự động cho mọi ticket hay chỉ ticket cần thực thi?
9. Có cần Billing/Timesheet cho Pay-per-Incident ngay Phase 1 không?
10. Customer có được xem file hợp đồng PDF trực tiếp không?

## 2. Phases

### Phase 0 — Foundation

Customer, User, Role, Service, Contract, Portal authentication.

### Phase 1 — Ticket Core

Create, assignment, timeline, comments, attachments, resolve, confirm, reopen.

### Phase 2 — SLA & Notification

SLA timers, escalation, email event engine, audit, email log.

### Phase 3 — Contract Alert

Alert rules, 3 alert instances, scheduler, retry, dashboard, renewal history.

### Phase 4 — Management Dashboard

Customer, IT Owner, IT Lead, Sales, contract risk, service performance.

### Phase 5 — Bitrix24

Task synchronization, mapping, retry, integration dashboard.

### Phase 6 — MSP Advanced

Problem, Change, CMDB, Knowledge Base, billing/time tracking, automation/AI.

## 3. Definition of Done — MVP

MVP được coi là đạt khi:

- Customer tạo được Ticket.
- Ticket đi đúng IT Owner.
- IT Owner assign đúng Support.
- Support xử lý và Resolve.
- Customer confirm hoặc Reopen.
- Reopen tạo escalation.
- Contract được quản lý và hiển thị đúng scope.
- 3 alert hoạt động, có log, không gửi trùng.
- Dashboard cho IT Owner/Lead hoạt động.
- Audit đầy đủ cho các event chính.

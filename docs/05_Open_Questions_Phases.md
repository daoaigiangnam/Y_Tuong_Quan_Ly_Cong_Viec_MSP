# 05 — Open Questions & Implementation Phases

## 1. Những quyết định cần BOD/Product chốt

Các câu hỏi dưới đây **không phải blocker để xây Core Product**. Chúng được thiết kế như business policies có thể cấu hình sau khi Product đã chạy.

Chi tiết kiến trúc cấu hình xem: [07 — Configuration-Driven Business Decisions](07_Configuration_Decisions.md).

| # | Quyết định | Policy/Configuration |
|---|---|---|
| 1 | Contract type: Full Package / Per Incident / Hourly / Hybrid? | `contract_types` |
| 2 | SLA theo Service hay Contract + Service? | `sla_scope_policy` |
| 3 | Customer có được chọn Priority? | `customer_priority_mode` |
| 4 | Resolved → Closed auto-close sau bao nhiêu ngày? | `auto_close_enabled` + `customer_response_days` |
| 5 | Alert mặc định 90/60/30 hay theo Contract? | Alert Profile + Contract Override |
| 6 | Email Customer ở cả 3 mốc hay chỉ Internal? | Alert Recipient Policy |
| 7 | Có nhiều IT Owner theo từng Service? | Service Assignment Policy |
| 8 | Có tạo Task khi IT Owner gán Support? | Task Creation Policy |
| 9 | Billing/Timesheet Phase 1? | Feature Flags + Billing Policy |
| 10 | Customer xem PDF hợp đồng? | Contract Document Visibility Policy |

### Nguyên tắc chốt

BOD/Product có thể chốt từng policy theo thời điểm kinh doanh. Product không được hard-code các giá trị trên vào Ticket/Contract engine.

```text
BOD/Product Decision
        ↓
Configuration / Policy
        ↓
Effective Date / Version
        ↓
Business Engine
        ↓
Audit Log
```

---

## 2. Implementation Phases

### Phase 1 — Foundation

- Authentication
- RBAC
- Customer
- Contact
- Service
- Contract core
- Configuration framework
- Audit

### Phase 2 — Contract

- Contract CRUD
- Contract Types
- Contract Services
- Owners / Sales
- Contract Documents
- Renewal
- Alert Profiles
- Alert History

### Phase 3 — Ticket

- Ticket CRUD
- Assignment
- Queue
- State Machine
- Timeline
- Public/Internal Comments
- Attachments
- Reopen
- Escalation

### Phase 4 — SLA

- SLA Policies
- Contract + Service SLA
- Response/Resolution timers
- Warning/Breach
- SLA dashboard

### Phase 5 — Portals

- Shared Design System
- Customer Portal
- Service Portal
- Role/Permission/Scope-driven navigation
- Shared Ticket UI with role-specific visibility/actions

### Phase 6 — Notification

- Email templates
- SMTP/provider abstraction
- Email queue
- Retry
- Ticket notifications
- Contract alerts
- Reopen alerts

### Phase 7 — Optional Integrations

Bitrix24 REST is **not part of the Core Product/MVP**.

Integration boundary will remain available for future:

```text
ITSM Ticket
    ↕ adapter/API
Bitrix24 Task
```

Ticket remains System of Record.

### Phase 8 — Advanced ITSM

- Problem
- Change
- Knowledge Base
- CMDB
- Reporting
- Billing/Timesheet if business policy is enabled

---

## 3. Definition of Done per Phase

Một Phase chỉ được chuyển trạng thái `DONE` khi:

- PHP lint PASS
- Database migration PASS
- Functional tests PASS
- Permission tests PASS
- Business rules PASS
- UI/UX checks PASS
- Regression PASS
- GitHub Actions GREEN
- Audit/logging PASS nếu có data mutation

```text
CODE
 ↓
LINT
 ↓
TEST
 ↓
GITHUB PASS
 ↓
COMMIT
 ↓
NEXT PHASE
```

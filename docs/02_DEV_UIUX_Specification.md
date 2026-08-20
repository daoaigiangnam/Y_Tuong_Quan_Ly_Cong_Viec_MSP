# 02 — DEV / Product / UIUX Specification

## 1. Phạm vi

Tài liệu này là baseline để Product, UX/UI, Backend và Frontend triển khai 2 module lõi:

- Customer / Ticket Management.
- Contract Management & Contract Expiry Alert.

Kiến trúc portal được chốt như sau:

- **External/Internal Portal của Công ty Dịch vụ**: một giao diện dùng chung cho IT Owner, IT Support, IT Lead và các team/role khác của Công ty Dịch vụ. Phạm vi chức năng được quyết định bằng RBAC + object scope, không hard-code theo chức danh “IT”.
- **Customer Portal**: giao diện riêng cho khách hàng, cùng design system và cùng ticket object nhưng có navigation, dữ liệu, action và permission riêng.
- **Bitrix24 REST Integration**: để mở, chưa phải dependency của core product. ITSM phải hoạt động đầy đủ khi Bitrix24 không được cấu hình.

## 2. Vai trò người dùng

| Role | Portal | Scope |
|---|---|---|
| Customer Admin | Customer Portal | Users/contact, Contracts/Tickets trong Customer |
| Customer User | Customer Portal | Tickets và dữ liệu Customer được cấp quyền |
| Service Owner / IT Owner | Service Portal | Customer được phân phụ trách; điều phối Ticket |
| Support Agent | Service Portal | Ticket được assign tới user/group |
| Team Lead / IT Lead | Service Portal | Toàn bộ scope được cấp, escalation, report |
| Sales | Service Portal | Customer/Contract thuộc scope Sales |
| Contract Admin | Service Portal | Quản lý Contract/Alert policy |
| Other Service Team | Service Portal | Theo role/group/permission; có thể là Network, Security, HR, Facilities... |
| System | System | Workflow, SLA, notification, scheduler, audit |

> **Nguyên tắc:** “IT Owner / IT Support / IT Lead” là các role nghiệp vụ mẫu. Mô hình permission phải cho phép Công ty Dịch vụ tạo thêm bất kỳ role/group nào mà không phải sửa code workflow.

## 3. Portal Architecture — Một Design System, Hai Experience

### 3.1. Nguyên tắc

Hai portal phải **trong suốt về mặt UI/UX**, nghĩa là:

- Cùng typography.
- Cùng spacing/grid.
- Cùng component library.
- Cùng màu trạng thái.
- Cùng cách hiển thị Ticket ID, Status, Priority, SLA, Timeline.
- Cùng pattern Search / Filter / Table / Detail / Timeline / Modal.
- Cùng responsive behavior.
- Cùng terminology đối với object dùng chung.

Nhưng **không dùng chung navigation và không hiển thị cùng một dữ liệu**.

```text
                    SHARED DESIGN SYSTEM
                            │
              ┌─────────────┴─────────────┐
              │                           │
      SERVICE PORTAL                CUSTOMER PORTAL
      (Công ty Dịch vụ)              (Khách hàng)
              │                           │
      ┌───────┼────────┐          ┌───────┼────────┐
      │       │        │          │       │        │
    Owner   Support   Lead      Tickets Contracts Profile
      │       │        │          │       │
      └───────┴────────┘          └───────┴────────┘
              │                           │
              └──────── SAME TICKET ──────┘
                         OBJECT
```

### 3.2. Không tạo hai Ticket system

Customer và Service Portal cùng đọc/ghi **một Ticket object trong ITSM database**.

Không được làm:

```text
Customer Portal Ticket DB
        ≠
Internal Portal Ticket DB
```

Phải làm:

```text
Customer Portal ──┐
                   ├── ITSM Ticket Service ── MySQL
Service Portal ────┘
```

Như vậy Customer cập nhật Ticket thì Service Team thấy ngay; Service Team cập nhật trạng thái thì Customer thấy ngay theo field/action được phép công khai.

## 4. Information Architecture

### 4.1. Service Portal — Công ty Dịch vụ

Portal này là giao diện làm việc chung của toàn bộ nhân sự Công ty Dịch vụ có quyền xử lý dịch vụ.

- Dashboard
- My Work / My Queue
- Customers
- Contracts
- Tickets
- SLA / Escalation
- Services
- Reports
- Notifications
- Knowledge Base (phase sau)
- Administration (theo permission)

Navigation phải **dynamic theo permission**. Ví dụ một user chỉ có quyền Contract Admin sẽ không thấy menu Tickets nếu không được cấp quyền.

### 4.2. Customer Portal — Khách hàng

- Dashboard
- Tickets
- Create Ticket
- Contracts
- Services
- Notifications
- Profile / Contacts

Customer không thấy menu nội bộ như:

- Internal Queue.
- SLA breach details nội bộ.
- Internal Assignment.
- Internal Notes.
- Cost/Margin/Commission.
- Internal Escalation.
- Audit kỹ thuật không được công khai.

## 5. UX Principles

1. **Một Design System, hai experience**: cảm giác sử dụng phải nhất quán nhưng thông tin và action khác nhau.
2. **Ticket là object trung tâm**: mọi thay đổi hiển thị trên timeline theo permission.
3. **Trạng thái luôn nhìn thấy**: badge + label + timestamp.
4. **SLA luôn nhìn thấy trên Service Portal**; Customer chỉ thấy SLA summary/target nếu policy cho phép.
5. **Action theo role**: UI chỉ hiển thị action mà user có permission; backend vẫn phải enforce permission.
6. **Không xoá lịch sử**: audit append-only.
7. **Form giảm lựa chọn thủ công**: Customer không tự chọn Contract nếu hệ thống xác định được từ Service.
8. **Exception-first dashboard**: Manager/Lead nhìn việc cần xử lý trước.
9. **Object-level security**: có permission chưa đủ; phải kiểm tra user có quyền trên Customer/Contract/Ticket đó hay không.
10. **Không phụ thuộc Bitrix24**: core workflow không được fail chỉ vì integration chưa cấu hình hoặc API ngoài bị lỗi.

# 6. TICKET MANAGEMENT

## 6.1. Ticket State Machine

```text
NEW
 ↓
ASSIGNED
 ↓
IN_PROGRESS
 ├── WAITING_CUSTOMER
 ├── WAITING_VENDOR
 └── IN_PROGRESS
 ↓
RESOLVED
 ├── CUSTOMER_CONFIRMED → CLOSED
 └── CUSTOMER_REOPENED → REOPENED → IN_PROGRESS
```

### Quy tắc

- `NEW → ASSIGNED`: phải có Owner/Group.
- `ASSIGNED → IN_PROGRESS`: Support/Service Agent bắt đầu xử lý.
- `IN_PROGRESS → WAITING_CUSTOMER`: chỉ khi cần dữ liệu/feedback từ Customer.
- `IN_PROGRESS → RESOLVED`: bắt buộc Resolution Note.
- `RESOLVED → CLOSED`: Customer confirm hoặc auto-close theo policy.
- `RESOLVED → REOPENED`: Customer phải cung cấp Reopen Reason.
- `REOPENED`: lập tức tạo notification cho Service Owner + Team Lead + assigned Support theo notification policy.

## 6.2. UI-01 — Customer Dashboard

### Mục đích

Cho Customer biết ngay:

- Ticket đang mở.
- Ticket đang xử lý.
- Ticket đã hoàn thành.
- Ticket bị Reopen.
- Contract đang active/sắp hết hạn.

### Layout

```text
[Header]
Logo | Company | Notification | User

[Sidebar]
Dashboard
Tickets
Contracts
Services
Profile

[Content]
KPI cards
Open | In Progress | Resolved | Reopened

[Primary CTA]
+ Create New Ticket

[Recent Tickets]
ID | Subject | Service | Status | Updated
```

## 6.3. UI-02 — Create Ticket

### Fields

| Field | Rule |
|---|---|
| Service | Required |
| Subject | Required, 5–200 chars |
| Description | Required |
| Attachment | Optional, file type/size policy |
| Customer Contact | Auto from logged-in user |
| Contract | Auto by Customer + Service; selectable only when multiple valid contracts |
| Priority | System/Service Owner controlled; optionally customer urgency input |

### Validation

- Không cho submit nếu Service không thuộc Customer.
- Nếu không xác định được Contract → route vào manual review queue.
- Ticket phải nhận unique ID.

## 6.4. Ticket Create Backend Flow

![Ticket Sequence](images/04_ticket_sequence.svg)

Backend transaction:

1. Validate authenticated Customer.
2. Resolve Contact.
3. Resolve valid Contract.
4. Resolve Service.
5. Resolve SLA.
6. Calculate SLA due timestamps.
7. Determine Service Owner / Assignment Group.
8. Persist Ticket + initial history.
9. Commit.
10. Queue notifications asynchronously.

## 6.5. UI-03 — Service Portal Dashboard

Đây là dashboard chung cho **IT Owner, IT Support, IT Lead và các role khác của Công ty Dịch vụ**.

### KPI được tính theo permission/scope của user

- My Customers.
- My Open Tickets.
- My Queue.
- SLA At Risk.
- SLA Breached.
- Reopened.
- Unassigned.
- Escalated.

### Ticket table

Columns tùy permission:

`Ticket ID | Customer | Contract | Service | Priority | Status | SLA Timer | Assignment Group | Assigned Agent | Updated | Actions`

### Filters

Customer, Contract, Service, Priority, Status, Assignment Group, Assigned Agent, SLA state, date range.

### Dynamic workspace

- **Service Owner**: ưu tiên Customers + điều phối + SLA + escalation.
- **Support Agent**: ưu tiên My Queue + Worklog + Waiting + Resolve.
- **Team Lead**: ưu tiên Exceptions + workload + SLA breach + escalation.
- **Sales**: ưu tiên Customer + Contract + renewal, không cần xem internal technical actions.
- **Other Service Team**: giao diện giống nhau, dữ liệu/action theo permission.

## 6.6. UI-04 — Ticket Detail — Shared Experience

Ticket Detail dùng cùng component ở cả hai portal nhưng render khác nhau theo `viewer_context`.

### Common sections

- Ticket header.
- Subject.
- Customer.
- Service.
- Status.
- Priority.
- Timeline.
- Public conversation.
- Attachments được phép xem.

### Service Portal thêm

- Contract.
- SLA timer/details.
- Assignment Group.
- Assigned Agent.
- Internal Notes.
- Worklog.
- Escalation.
- Internal metadata.
- Operational actions.

### Customer Portal thêm

- Customer-visible conversation.
- Customer-visible attachments.
- Resolution summary.
- Confirm / Reopen.

### Tuyệt đối không dùng CSS để “che” dữ liệu nhạy cảm

Backend phải lọc field và action trước khi render. CSS/JS chỉ là lớp UX, không phải security boundary.

## 6.7. UI-05 — Service Agent Queue

Service Agent có thể là IT Support hoặc bất kỳ team xử lý nào.

### KPI

New / In Progress / Waiting Customer / SLA Warning / Reopened.

### Ticket action

- Accept.
- Start Work.
- Add Worklog.
- Ask Customer.
- Attach evidence.
- Resolve.

### Resolve validation

Required:

- Resolution Summary.
- Resolution Category.
- Optional Worklog duration.
- Root cause field only if policy requires.

## 6.8. UI-06 — Customer Confirm / Reopen

When status = `RESOLVED`:

```text
Did this resolve your issue?

[ YES — CLOSE TICKET ]
[ NO — REOPEN TICKET ]
```

Reopen modal:

- Reopen reason: required.
- Attachment: optional.
- Submit.

After submit:

- Ticket status = `REOPENED`.
- `reopen_count += 1`.
- Create ticket history.
- Generate alerts.

## 6.9. Reopen Escalation Rules

Minimum rule:

```text
Customer Reopen
→ notify Service Owner
→ notify Team Lead
→ notify current Support Agent
```

Optional rules:

- Reopen count >= 2 → escalate priority.
- Reopen count >= 3 → flag `problem_candidate = true`.
- Reopen after SLA resolved within configurable window → manager review.

## 6.10. IT Lead / Team Lead Dashboard

Manager sees exceptions:

- SLA breach.
- SLA at risk.
- P1/P2.
- Reopened.
- Unassigned > threshold.
- Customer escalation.
- Agent overload.

# 7. CONTRACT MANAGEMENT

## 7.1. Contract Lifecycle

```text
DRAFT
 ↓
PENDING_SIGNATURE
 ↓
ACTIVE
 ↓
EXPIRING
 ├── RENEWED → ACTIVE (new period / new version)
 └── EXPIRED
```

## 7.2. UI-07 — Contract List

Columns:

`Contract No | Customer | Service | Start Date | End Date | Status | Alert #1 | Alert #2 | Alert #3 | Owner | Sales | Actions`

### Status rules

- `ACTIVE`: còn hiệu lực và chưa vào ngưỡng Expiring.
- `EXPIRING`: nằm trong policy window.
- `EXPIRED`: End Date < current date và chưa có renewal hiệu lực.
- `RENEWED`: contract version cũ đã được thay bằng period mới.

## 7.3. UI-08 — Contract Detail

Sections:

1. Contract Summary.
2. Customer.
3. Services.
4. SLA.
5. Contract Dates.
6. Contacts.
7. Attachments.
8. Internal Owners.
9. Alert Policy.
10. Alert History.
11. Renewal History.
12. Audit Timeline.

## 7.4. Customer Portal — Contract Detail

Customer được xem các field được whitelist:

- Contract number.
- Service.
- Start date.
- End date.
- Status.
- Scope of service.
- SLA summary nếu commercial policy cho phép.
- Approved contract document nếu policy cho phép.

Không show mặc định:

- Cost.
- Margin.
- Commission.
- Internal note.
- Internal escalation.

## 7.5. Alert Rule

Không hard-code 90/60/30. Dữ liệu dạng policy:

```text
contract_alert_rules
- alert_no
- trigger_days_before_expiry
- enabled
- recipient_policy
- template_id
```

Ví dụ:

```text
Rule A: 90 / 60 / 30
Rule B: 30 / 15 / 7
```

## 7.6. UI-09 — Alert History

Columns:

`Alert # | Scheduled Date | Sent Date | Status | To | CC | Template | Message ID | Retry Count | Error`

Status:

- PENDING.
- SENT.
- FAILED.
- SKIPPED.
- CANCELLED.

## 7.7. Alert Engine

![Contract Alert Engine](images/05_contract_alert.svg)

Daily scheduler:

```text
Scheduler
→ find active contracts
→ calculate due alerts
→ check alert instance already sent
→ create pending instance
→ queue email
→ update result
→ audit
```

### Idempotency

Unique key khuyến nghị:

`contract_id + alert_no + expiry_date`

Không được gửi lại cùng Alert Instance do cron chạy nhiều lần.

## 7.8. Email recipient policy

Internal:

- To: Owner.
- CC: Team Lead + Sales.

Customer:

- Email riêng cho Customer Contract Contact khi policy bật.

Không trộn recipient internal và external nếu template có dữ liệu nội bộ.

## 7.9. UI-10 — Contract Dashboard

KPI:

- Active.
- Expiring < 7 days.
- Expiring < 30 days.
- Expiring < 90 days.
- Expired.
- Renewal in progress.

Exception list:

- Alert failed.
- Contract expiring nhưng chưa có owner.
- Contract expiring nhưng chưa có renewal activity.

# 8. NOTIFICATION SPECIFICATION

## Ticket events

| Event | Customer | Owner | Team Lead | Assigned Agent |
|---|---:|---:|---:|---:|
| Ticket Created | ✅ | ✅ | tùy policy | nếu assigned |
| Assigned | optional | ✅ | optional | ✅ |
| Status Changed | ✅ | ✅ | optional | ✅ |
| Resolved | ✅ | ✅ | optional | ✅ |
| Customer Reopen | ✅ confirmation | ✅ | ✅ | ✅ |
| SLA Breach | optional | ✅ | ✅ | ✅ |

## Contract events

| Event | Customer | Owner | Team Lead | Sales |
|---|---:|---:|---:|---:|
| Alert #1 | policy | ✅ | ✅ | ✅ |
| Alert #2 | policy | ✅ | ✅ | ✅ |
| Alert #3 | policy | ✅ | ✅ | ✅ |
| Expired | policy | ✅ | ✅ | ✅ |
| Renewal | ✅/policy | ✅ | ✅ | ✅ |

## Email requirements

Mỗi email phải có:

- event code.
- template version.
- timestamp.
- recipient list.
- status.
- provider/message ID nếu có.
- retry count.

# 9. BITRIX24 INTEGRATION — RESERVED / FUTURE

Bitrix24 **không thuộc MVP/core dependency**.

Mục tiêu hiện tại là thiết kế ITSM sao cho sau này có thể tích hợp mà không phải thay đổi Ticket domain.

## 9.1. Nguyên tắc

- ITSM là System of Record cho Ticket.
- Bitrix24 là tùy chọn Internal Collaboration / Task Execution.
- Không yêu cầu Bitrix24 để create, assign, resolve hoặc close Ticket.
- Không đặt business logic Ticket trong Bitrix24.
- Khi chưa cấu hình Bitrix24, toàn bộ Service Portal vẫn hoạt động bình thường.

## 9.2. Integration boundary cần chừa sẵn

Có thể tạo abstraction/service interface sau này:

```text
Ticket Domain
      │
      ▼
Integration Adapter
      │
      ├── Bitrix24 Adapter (future)
      └── Other Adapter (future)
```

Mapping dự kiến:

```text
ITSM ticket_id ↔ external_system ↔ external_object_id
```

Không hard-code `bitrix_task_id` vào core Ticket nếu có thể tránh. Nên dùng bảng integration mapping.

## 9.3. Failure handling khi tích hợp sau này

Nếu external API lỗi:

- Ticket ITSM vẫn hoạt động.
- Ghi integration log.
- Retry queue.
- Hiển thị `Sync Pending` cho Service Owner nếu cần.

# 10. UI COMPONENT GUIDELINES

## Status

Dùng cả **màu + text + icon**; không dùng màu đơn độc.

## Priority

P1 Critical / P2 High / P3 Medium / P4 Low.

## SLA

Service Portal: Time remaining / At Risk / Breached.

Customer Portal: chỉ hiển thị SLA target/status theo policy công khai.

## Tables

Search, Filter, Sort, Pagination, Export theo permission, Column preference.

## Forms

Required indication, server-side validation, inline error, prevent double submit, unsaved changes warning.

## Shared UI Components

Nên xây component theo object, không theo portal:

```text
TicketHeader
TicketStatusBadge
TicketPriorityBadge
TicketTimeline
TicketAttachmentList
TicketCommentComposer
ContractSummary
SlaIndicator
CustomerSummary
NotificationBell
DataTable
FilterBar
```

Portal chỉ quyết định component nào được render và field/action nào được phép.

Ví dụ:

```php
$context = PortalContext::fromUser($user);
$ticketView = TicketPresenter::forContext($ticket, $context);
```

Không tạo một bộ Ticket UI riêng cho Customer và một bộ Ticket UI hoàn toàn khác cho Internal nếu không cần thiết.

# 11. SECURITY / AUDIT

Tất cả mutation quan trọng phải có audit:

- actor.
- action.
- object type.
- object id.
- old/new value nếu phù hợp.
- timestamp.
- IP / user-agent theo security policy.

Actions bắt buộc audit: ticket assignment, priority/status change, reopen, resolve, close, contract create/update, alert rule update, renewal, permission change.

### Object-level authorization

Mỗi request phải kiểm tra:

```text
Authenticated User
      ↓
Role Permission
      ↓
Portal Context
      ↓
Object Scope
      ↓
Field Visibility
      ↓
Action Permission
```

Không được suy ra quyền chỉ từ URL hoặc menu.

# 12. NON-FUNCTIONAL REQUIREMENTS

- List pages dùng server-side pagination.
- Dashboard query có index/aggregation phù hợp.
- Email/notification chạy asynchronous.
- Retry có backoff.
- Idempotency cho Alert.
- RBAC + customer/object scope.
- Attachment access control.
- Session timeout theo policy.
- Application / integration / email / scheduler logs.
- Bitrix24 không phải runtime dependency của core.

# 13. DEV DELIVERY CHECKLIST

### Portal Foundation

- [ ] Shared Bootstrap/CSS design system.
- [ ] Service Portal shell.
- [ ] Customer Portal shell.
- [ ] Dynamic navigation by permission.
- [ ] Shared Ticket components.
- [ ] PortalContext / object-scope authorization.
- [ ] Field visibility policy.

### Ticket

- [ ] Customer Portal create ticket.
- [ ] Contract/service auto-resolution.
- [ ] SLA calculation.
- [ ] Service Owner assignment.
- [ ] Support/Agent assignment.
- [ ] Shared timeline.
- [ ] Internal vs public comments.
- [ ] Resolve.
- [ ] Customer confirm.
- [ ] Reopen with reason.
- [ ] Reopen escalation.
- [ ] SLA warning/breach.
- [ ] Notification log.

### Contract

- [ ] Contract CRUD.
- [ ] Contract services.
- [ ] Contract document.
- [ ] Customer visibility.
- [ ] Alert policy.
- [ ] Alert instance.
- [ ] Email schedule.
- [ ] Email history.
- [ ] Renewal history.
- [ ] Expired state.

### Platform

- [ ] RBAC.
- [ ] Customer-scoped access.
- [ ] Object-level authorization.
- [ ] Audit.
- [ ] Search/filter/export.
- [ ] Integration boundary only; Bitrix24 remains optional.
- [ ] Scheduler monitoring.

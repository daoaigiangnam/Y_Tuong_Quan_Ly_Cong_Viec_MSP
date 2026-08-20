# 06 — Portal Architecture: Shared UI/UX, Different Experiences

## 1. Mục tiêu

Hệ thống có hai nhóm người dùng hoàn toàn khác nhau:

1. **Khách hàng** — cần một Customer Portal đơn giản, minh bạch, tập trung vào yêu cầu, hợp đồng và giao tiếp.
2. **Công ty Dịch vụ** — cần một Service Portal dùng chung cho mọi nhân sự có quyền vận hành dịch vụ: IT Owner, Support Agent, Team Lead, Sales, Contract Admin, Network, Security, Application, Onsite... tùy tổ chức.

Mục tiêu UX là:

> **Một hệ thống nhìn như một sản phẩm duy nhất, nhưng mỗi đối tượng chỉ nhìn thấy đúng những gì họ cần và được phép làm.**

## 2. Không thiết kế “hai hệ thống”

Không nên:

```text
Customer UI → Customer Ticket Logic
Internal UI → Internal Ticket Logic
```

Vì cách này dễ dẫn đến duplicate code, khác trạng thái, khác validation và khó đồng bộ.

Nên:

```text
                 SHARED DOMAIN
                     │
          ┌──────────┴──────────┐
          │                     │
   Customer Portal        Service Portal
          │                     │
          └──────────┬──────────┘
                     ▼
              Ticket Service
                     ▼
                  MySQL
```

Hai portal chỉ khác **presentation + permission + object scope + available actions**.

## 3. Shared Design System

### Shared

- Bootstrap grid.
- Typography.
- Spacing.
- Buttons.
- Badges.
- Alerts.
- Cards.
- DataTables pattern.
- Form controls.
- Modal.
- Timeline.
- Attachment viewer.
- Status/priority/SLA indicators.
- Empty state.
- Loading state.
- Error state.

### Khác nhau

| Thành phần | Customer | Service Team |
|---|---|---|
| Navigation | Customer-focused | Operations-focused |
| Customer data | Own company only | According to scope |
| Internal note | Không | Có |
| Assignment | Không | Có |
| SLA detail | Public policy | Full operational |
| Escalation | Không | Có |
| Worklog | Không/summary | Có |
| Contract cost | Không | Role-based |
| Reopen | Có | Có thể xử lý hậu quả |
| Reports | Customer scope | Operational scope |

## 4. Portal Context

Backend phải xác định context trước khi render:

```text
Authenticated User
       ↓
Portal Type
       ↓
Role(s)
       ↓
Permission(s)
       ↓
Customer/Object Scope
       ↓
Field Visibility
       ↓
Available Actions
```

Ví dụ:

```php
$context = PortalContext::resolve($user);
$ticket = TicketService::getForViewer($ticketId, $context);
```

`TicketService` trả về một view model đã được authorization/field filtering.

## 5. Ticket Detail — cùng component, khác dữ liệu

### Shared shell

```text
┌─────────────────────────────────────────────┐
│ INC-2026-00125   Email không gửi được       │
│ ABC Corporation   P2   IN PROGRESS          │
├─────────────────────────────────────────────┤
│ Description                                 │
│                                             │
│ Timeline                                    │
│ 10:02 Created                               │
│ 10:10 Assigned                              │
│ 10:20 Reply                                 │
│                                             │
│ Attachments                                 │
└─────────────────────────────────────────────┘
```

### Customer Portal

```text
Header
 ├─ Status
 ├─ Public conversation
 ├─ Public attachments
 ├─ Resolution summary
 └─ [Confirm] [Reopen]
```

### Service Portal

```text
Header
 ├─ Status / Priority / SLA
 ├─ Customer / Contract / Service
 ├─ Assignment Group / Agent
 ├─ Public conversation
 ├─ Internal notes
 ├─ Worklog
 ├─ Escalation
 └─ Operational actions
```

**Quan trọng:** Internal Note không phải là một message có CSS `display:none` ở Customer Portal. Nó phải không xuất hiện trong response/view model dành cho Customer.

## 6. Navigation

### Customer Portal

```text
Dashboard
Tickets
Contracts
Services
Notifications
Profile
```

### Service Portal

```text
Dashboard
My Work
Customers
Tickets
Contracts
Services
SLA / Escalation
Reports
Notifications
Administration
```

Menu phải dynamic theo permission.

## 7. Dashboard theo role

Service Portal không tạo dashboard riêng thành các hệ thống độc lập. Dùng cùng dashboard framework với widget theo permission.

### Support Agent

```text
My Queue
New
In Progress
Waiting
SLA Warning
Reopened
```

### Service Owner

```text
My Customers
Open Tickets
SLA Risk
Unassigned
Reopened
Contract Expiry
```

### Team Lead

```text
SLA Breach
P1/P2
Escalation
Agent Workload
Reopened
Customer Escalation
```

### Sales

```text
Customers
Contracts
Expiring
Renewal
Commercial KPI
```

## 8. Customer Scope vs Service Scope

### Customer

```text
user.customer_id = X
        ↓
only Customer X
```

### Service Team

Không mặc định thấy toàn bộ database. Có thể scope theo:

- Role.
- Assignment Group.
- Customer ownership.
- Service ownership.
- Region/Branch.
- Contract.
- Explicit object permission.

## 9. Action Matrix

| Action | Customer | Agent | Owner | Lead | Sales |
|---|---:|---:|---:|---:|---:|
| Create Ticket | ✅ | optional | optional | optional | optional |
| Comment Public | ✅ | ✅ | ✅ | ✅ | policy |
| Internal Note | ❌ | ✅ | ✅ | ✅ | ❌ |
| Assign Agent | ❌ | ❌/policy | ✅ | ✅ | ❌ |
| Change Priority | ❌ | policy | ✅ | ✅ | ❌ |
| Resolve | ❌ | ✅ | ✅ | ✅ | ❌ |
| Close | ✅ confirm | policy | policy | ✅ | ❌ |
| Reopen | ✅ | policy | policy | policy | ❌ |
| View Contract | own | scope | scope | scope | scope |

Đây chỉ là baseline; quyền thực tế phải nằm trong permission matrix/configuration.

## 10. API/Backend rule

Frontend không được quyết định security.

Mỗi endpoint phải thực hiện:

```text
Authentication
→ Authorization
→ Object Scope
→ Field Visibility
→ Action Validation
→ Mutation
→ Audit
```

## 11. Shared URL strategy

Có thể dùng một application host với portal context:

```text
/app/...
/portal/...
```

hoặc:

```text
service.example.com
customer.example.com
```

Nhưng không nên duplicate business logic. Cả hai gọi cùng Service Layer.

## 12. Bitrix24 — để mở

Bitrix24 là integration option trong tương lai.

Core product không được phụ thuộc vào:

- Bitrix token.
- Bitrix task ID.
- Bitrix webhook.
- Bitrix availability.

Nên chừa abstraction:

```text
IntegrationManager
       │
       ├── Bitrix24Adapter (future)
       ├── EmailAdapter
       └── OtherAdapter
```

Mapping external object dùng bảng riêng:

```text
integration_mappings
- id
- provider
- object_type
- local_object_id
- external_object_id
- sync_status
- last_synced_at
- last_error
```

## 13. Acceptance Criteria

### UX

- [ ] Customer và Service Team nhìn thấy cùng Ticket ID/status/timeline public.
- [ ] Component style thống nhất.
- [ ] Navigation khác nhau theo portal.
- [ ] Dashboard khác nhau theo role.
- [ ] Customer không thấy internal data.
- [ ] Service Team không bị giới hạn vào role “IT”; role/group có thể cấu hình.

### Security

- [ ] Internal Note không nằm trong Customer API response.
- [ ] Customer không thể sửa URL để xem Ticket của Customer khác.
- [ ] Customer không thể gọi action nội bộ bằng POST thủ công.
- [ ] Permission được kiểm tra ở backend.
- [ ] Object scope được kiểm tra ở backend.

### Architecture

- [ ] Một Ticket domain/service.
- [ ] Một database source of truth.
- [ ] Không duplicate Ticket workflow giữa hai portal.
- [ ] Bitrix24 không phải dependency.
- [ ] Có integration boundary cho tương lai.

## 14. Kết luận

Thiết kế này giúp sản phẩm có cảm giác **“một hệ thống duy nhất”** thay vì hai website ghép lại:

```text
                 ONE PRODUCT
                     │
          ┌──────────┴──────────┐
          │                     │
     Customer UX          Service UX
          │                     │
          └──────────┬──────────┘
                     │
               SAME DOMAIN
                     │
          Customer → Contract
                     ↓
                  Service
                     ↓
                   Ticket
                     ↓
                    SLA
```

Đây là kiến trúc nên chốt trước khi tiếp tục xây Ticket UI/UX và RBAC sâu hơn.

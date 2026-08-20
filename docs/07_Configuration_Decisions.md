# 07 — Configuration-Driven Business Decisions

## Mục tiêu

Hệ thống phải cho phép BOD/Product **chốt nghiệp vụ sau khi Product đã được triển khai** mà không phải sửa source code cho các quyết định nghiệp vụ có thể cấu hình.

Nguyên tắc:

> **Code workflow engine một lần; lưu business policy/configuration trong database; UI đọc configuration để quyết định field, option, rule, notification và workflow.**

Không hard-code các quyết định như `FULL_PACKAGE`, `90/60/30`, `auto close 3 days`, hoặc `customer can choose priority` trong PHP.

---

## 1. Ten business decisions cần chốt

| # | Quyết định | Thiết kế Product |
|---|---|---|
| 1 | Contract type | Configurable Contract Types |
| 2 | SLA theo Service hay Contract + Service | SLA Policy Scope |
| 3 | Customer chọn Priority? | Portal Permission/Policy |
| 4 | Resolved → Closed auto-close | Closure Policy |
| 5 | Contract Alert 90/60/30 hay riêng từng Contract | Alert Profile + Contract Override |
| 6 | Email Customer ở 3 mốc? | Recipient Policy |
| 7 | Nhiều IT Owner theo Service? | Service Assignment Policy |
| 8 | Có tạo Task khi gán Support? | Task Creation Policy |
| 9 | Billing/Timesheet Phase 1? | Feature Flag + Billing Policy |
| 10 | Customer xem PDF hợp đồng? | Contract Document Visibility Policy |

---

## 2. Contract Type

Không hard-code 4 loại trong PHP. Tạo bảng `contract_types`:

```text
contract_types
- id
- code
- name
- description
- billing_model
- requires_timesheet
- requires_incident_charge
- active
- sort_order
```

Ví dụ dữ liệu mặc định:

```text
FULL_PACKAGE
PER_INCIDENT
HOURLY
HYBRID
```

BOD có thể chọn/đổi tên/kích hoạt loại hợp đồng từ Admin > Settings > Contract Types.

### UI

```text
Contract Types

[+ Add Contract Type]

Code          Name             Billing       Active
FULL_PACKAGE  Full Package     Fixed         ✓
PER_INCIDENT  Per Incident     Incident      ✓
HOURLY        Hourly           Timesheet     ✓
HYBRID        Hybrid           Mixed        ✓
```

**Không cho xóa Contract Type đã được sử dụng; chỉ Deactivate.**

---

## 3. SLA Scope

Hỗ trợ 2 policy:

```text
SERVICE_ONLY
CONTRACT_SERVICE
```

Resolution priority:

```text
Contract + Service SLA
        ↓ nếu không có
Service SLA
        ↓ nếu không có
System Default SLA
```

Cho phép BOD/Product đổi policy bằng configuration, không sửa code.

---

## 4. Customer Priority Policy

Configuration:

```text
customer_priority_mode
----------------------
DISABLED
SUGGEST_ONLY
ALLOW
```

- `DISABLED`: Customer không thấy Priority.
- `SUGGEST_ONLY`: Customer chọn Urgency/Impact; hệ thống tính Priority.
- `ALLOW`: Customer được chọn Priority trong danh sách được phép.

Khuyến nghị MVP: `SUGGEST_ONLY`.

---

## 5. Resolved → Closed

Bảng `ticket_closure_policies` hoặc system setting:

```text
auto_close_enabled
customer_response_days
reopen_allowed_after_close
```

Ví dụ:

```text
Resolved
   ↓
Wait Customer = 3 days
   ↓
No response
   ↓
Closed automatically
```

Nếu Customer phản hồi trong thời gian chờ → không auto-close.

---

## 6. Contract Alert Schedule

Không hard-code 90/60/30.

Tạo:

```text
contract_alert_profiles
- id
- code
- name
- active
```

và:

```text
contract_alert_profile_items
- profile_id
- alert_no
- days_before_expiry
- audience_policy
- email_template_id
- active
```

Ví dụ Profile mặc định:

```text
STANDARD
  #1 90 days
  #2 60 days
  #3 30 days
```

Một Contract có thể:

```text
use_default_alert_profile = true
```

hoặc override:

```text
30 / 15 / 7
```

### Rule

Contract-level override chỉ thay đổi lịch của Contract đó; không sửa Profile chung.

---

## 7. Customer Contract Alert Recipient

Mỗi alert có recipient policy:

```text
INTERNAL_ONLY
CUSTOMER_ONLY
INTERNAL_AND_CUSTOMER
```

Internal recipient có thể gồm:

```text
IT Owner
IT Lead
Sales
Contract Admin
```

Customer recipient lấy từ Contact/Contract Notification Contacts.

Không hard-code CC bằng email trong PHP.

---

## 8. Multiple IT Owner per Service

Thiết kế hỗ trợ ngay từ đầu:

```text
service_assignments
- service_id
- customer_id nullable
- contract_id nullable
- assignment_group_id
- primary_owner_id
- backup_owner_id
- effective_from
- effective_to
- priority
```

Resolution order:

```text
Contract + Service assignment
        ↓
Customer + Service assignment
        ↓
Service default assignment
        ↓
Assignment Group queue
```

Như vậy sau này có thể một Customer có:

```text
Network       → Nguyễn A
Server        → Nguyễn B
Security      → Nguyễn C
Application   → Team App
```

mà không sửa Ticket engine.

---

## 9. Ticket → Task Policy

Không mặc định Ticket nào cũng tạo Task.

Configuration:

```text
task_creation_mode
------------------
NEVER
ON_ASSIGNMENT
MANUAL
BY_SERVICE
BY_PRIORITY
```

Ví dụ:

```text
IT Owner assigns Support
        ↓
Policy = ON_ASSIGNMENT
        ↓
Create internal execution Task
```

Nếu Policy = MANUAL:

```text
[Create Task]
```

Ticket vẫn là System of Record.

Task chỉ là execution object.

Bitrix24 sau này có thể trở thành một Task Provider, nhưng không phải nguồn Ticket thứ hai.

---

## 10. Billing / Timesheet

Không cần khóa kiến trúc nếu Phase 1 chưa triển khai.

Dùng feature flags:

```text
billing_enabled
pay_per_incident_enabled
timesheet_enabled
invoice_integration_enabled
```

Nếu `billing_enabled = false`:

- không hiển thị Billing UI
- không bắt buộc Timesheet
- không tạo charge

Khi bật sau này:

```text
Ticket
 ↓
Worklog / Timesheet
 ↓
Billable?
 ↓
Rate
 ↓
Charge
 ↓
Invoice
```

Không phải sửa Ticket core.

---

## 11. Customer xem Contract PDF

Tạo visibility policy:

```text
contract_document_visibility
----------------------------
HIDDEN
METADATA_ONLY
CUSTOMER_VIEW
CUSTOMER_DOWNLOAD
```

Có thể cấu hình theo Document Type:

```text
Signed Contract       → CUSTOMER_VIEW
Pricing Appendix      → HIDDEN
Internal SLA Appendix → METADATA_ONLY
Technical Attachment  → CUSTOMER_DOWNLOAD
```

Đây là **authorization rule**, không chỉ là nút Download trên UI.

---

# 12. Configuration Architecture

```text
                    ADMIN / PRODUCT
                          │
                          ▼
                 Configuration Center
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
   Contract Policy    Ticket Policy     Notification
        │                 │                 │
        ▼                 ▼                 ▼
  Contract Engine     Ticket Engine      Email Engine
        │                 │                 │
        └─────────────────┼─────────────────┘
                          ▼
                       Database
```

---

# 13. Configuration Versioning

Các policy quan trọng nên có:

```text
policy_versions
- id
- policy_type
- policy_id
- version
- effective_from
- effective_to
- status
- created_by
- created_at
```

Mục đích:

> Hợp đồng cũ phải tiếp tục dùng policy cũ nếu policy mới được áp dụng cho hợp đồng mới.

Ví dụ:

```text
2026 Contract A
Alert Profile v1 = 90/60/30

2027 Product changes default
Alert Profile v2 = 120/60/30

Contract A
→ vẫn giữ v1

New Contract B
→ dùng v2
```

Không được lấy cấu hình hiện tại rồi áp ngược vào lịch sử.

---

# 14. Audit

Mọi thay đổi business configuration phải ghi:

```text
who
what
old_value
new_value
when
reason
```

Ví dụ:

```text
Admin Nguyễn A
Changed Contract Alert Profile
STANDARD
→ EXTENDED
Reason: BOD approved new renewal policy
2026-08-20 15:30
```

---

# 15. Product Rule

BOD/Product có thể quyết định nghiệp vụ **sau khi code Product** trong phạm vi các policy đã được thiết kế.

Nhưng không phải mọi thứ đều nên là dynamic configuration.

### Nên configurable

- Contract Types
- SLA Policy
- Priority Policy
- Auto Close
- Alert Schedule
- Recipients
- Assignment Rules
- Task Creation
- Billing Feature Flags
- Document Visibility
- Email Templates

### Không nên configurable tùy ý

- Database relationships
- Security model core
- Audit integrity
- Ticket identity
- Permission bypass
- Data ownership
- Financial calculation engine nếu chưa có test/approval

---

# 16. Acceptance Criteria

### AC-01
Admin có thể thêm Contract Type mà không sửa PHP.

### AC-02
Admin có thể đổi Alert Profile 90/60/30 thành 120/60/30.

### AC-03
Contract đã active giữ snapshot/profile version đã áp dụng.

### AC-04
Admin có thể chọn Customer Priority Policy.

### AC-05
Admin có thể bật/tắt Auto Close và số ngày chờ.

### AC-06
Admin có thể cấu hình recipient cho từng Contract Alert.

### AC-07
Một Customer có thể có nhiều Owner theo Service.

### AC-08
Task creation policy không làm thay đổi Ticket lifecycle.

### AC-09
Tắt Billing không làm mất dữ liệu Ticket/Contract.

### AC-10
Customer chỉ xem được Contract Document theo authorization policy.

### AC-11
Mọi thay đổi configuration có audit trail.

### AC-12
Configuration change không làm thay đổi lịch sử Ticket/Contract đã đóng.

---

# 17. Kết luận

Product phải được xây theo nguyên tắc:

> **BOD/Product chốt Policy → Admin cấu hình → Engine thực thi → Audit ghi nhận.**

Do đó các câu hỏi trong `05_Open_Questions_Phases.md` không phải blocker để code toàn bộ Product. Chúng chỉ là các **business policy có thể chốt và áp dụng về sau**, miễn là kiến trúc/configuration engine đã được chuẩn bị ngay từ đầu.

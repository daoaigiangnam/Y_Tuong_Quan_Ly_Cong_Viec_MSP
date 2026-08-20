# 08 — Product Decision Register & Development Traceability

## 1. Mục đích

Tài liệu này là **nguồn đối chiếu chính thức** giữa quyết định của BOD/Product, thiết kế nghiệp vụ, cấu hình hệ thống, UI/UX, database và code.

Nguyên tắc:

> BOD/Product quyết định Business Policy → Product ghi nhận Decision → Dev triển khai Engine/Configuration → Test xác nhận → Git commit là bằng chứng triển khai.

Không hard-code business decisions nếu decision có khả năng thay đổi theo thời gian.

---

## 2. Quy tắc trạng thái Decision

| Status | Ý nghĩa |
|---|---|
| PROPOSED | Đang đề xuất, chưa chốt |
| CONFIGURABLE | Kiến trúc đã hỗ trợ, BOD có thể chốt sau |
| APPROVED | BOD/Product đã chốt nghiệp vụ |
| IMPLEMENTED | Đã code |
| TESTED | Đã test và pass |
| RELEASED | Đã được đưa vào phiên bản chính thức |
| SUPERSEDED | Đã có quyết định mới thay thế |

Một decision chưa APPROVED không được tự ý biến thành business rule cứng trong code.

---

## 3. Decision Matrix

| ID | Business Decision | Thiết kế hệ thống | Configuration | UI/UX ảnh hưởng | Test cần có |
|---|---|---|---|---|---|
| DEC-001 | Contract Type | Contract Type Engine | `contract_types` | Contract Create/Edit | CRUD + type-specific rule |
| DEC-002 | SLA Scope | SLA Resolution Engine | `sla_scope_policy` | Service/Contract SLA display | SLA precedence |
| DEC-003 | Customer Priority | Priority Policy | `customer_priority_mode` | Create Ticket | Allowed/hidden/validated |
| DEC-004 | Auto Close | Closure Engine | `auto_close_enabled`, `customer_response_days` | Customer Ticket | timeout + close |
| DEC-005 | Contract Alert Schedule | Alert Profile Engine | Alert Profile + Contract Override | Contract Alert tab | due date + duplicate prevention |
| DEC-006 | Alert Recipients | Recipient Policy | recipient rules | notification preview/log | recipient matrix |
| DEC-007 | Multiple Owner | Assignment Engine | Service Assignment Policy | assignment UI | correct owner resolution |
| DEC-008 | Ticket → Task | Task Creation Policy | `task_creation_policy` | assignment action | auto/manual/never |
| DEC-009 | Billing/Timesheet | Feature Flag + Billing Policy | `billing_enabled` | menu/fields | disabled/enabled behavior |
| DEC-010 | Contract PDF Visibility | Document Authorization | visibility policy | document actions | backend authorization |
| DEC-011 | Bitrix24 | Integration Adapter Boundary | integration config | optional integration UI | adapter isolation |
| DEC-012 | Portal Experience | Shared Design System + RBAC | role/permission/scope | navigation/dashboard/fields/actions | role/scope visibility |

---

## 4. Contract Type Policy

Supported architecture must allow at minimum:

- FULL_PACKAGE
- PER_INCIDENT
- HOURLY
- HYBRID

The Product must not assume that only one or two types exist.

Future examples may include:

- PROJECT_BASED
- RETAINER
- BLOCK_OF_HOURS

Adding a type should normally be a configuration/data operation unless the type introduces a fundamentally new billing algorithm.

---

## 5. SLA Policy

SLA resolution must support precedence rather than hard-coded service values.

Recommended evaluation:

```text
Customer
  + Contract
  + Service
  + Priority
      ↓
SLA Policy Resolution
      ↓
Response Target
Resolution Target
Business Calendar
Escalation Policy
```

The exact precedence must be recorded as a Product Decision before release of SLA Engine.

---

## 6. Priority Policy

Customer Priority behavior is configurable:

```text
DISABLED
    → Customer cannot choose P1/P2/P3/P4

LIMITED
    → Customer selects business urgency only
    → System maps to operational priority

ALLOWED
    → Customer may select configured priorities
    → Server validates allowed values
```

UI hiding is not authorization. Backend must always validate.

---

## 7. Ticket Auto-Close Policy

Resolved is not automatically Closed unless policy allows it.

```text
RESOLVED
   ↓
Customer Confirmation
   ├── Confirm → CLOSED
   ├── Reopen → REOPENED
   └── No response → Auto-close only if policy enabled
```

Configurable values include:

- enabled/disabled
- response days
- reminder schedule
- whether customer receives final notice

---

## 8. Contract Alert Policy

Default profile may be:

```text
STANDARD
#1 = 90 days
#2 = 60 days
#3 = 30 days
```

But every Contract may reference a different Alert Profile or explicitly override it if Product permits.

Example:

```text
Contract A → STANDARD → 90/60/30
Contract B → ENTERPRISE → 180/90/30
```

Alert instances must be persisted so historical sent dates do not change when future configuration changes.

Duplicate protection must be based on a stable business key such as Contract + Alert Instance/Profile Version.

---

## 9. Alert Recipient Policy

Recipients must be policy-driven, not hard-coded in PHP.

Possible recipients:

- Customer Contact
- IT/Service Owner
- Service Team Lead
- Contract Owner
- Sales Owner
- Contract Administrator
- Additional configured recipients

Each alert instance can have its own recipient policy.

---

## 10. Assignment Policy

The system must support both:

### Single owner

```text
Customer + Service → One Owner
```

### Multiple owners / team assignment

```text
Customer + Service
       ↓
Assignment Group
       ↓
Members / Primary Owner
```

Do not hard-code role names such as `IT_OWNER` as the only possible operational actor.

The generic concept is **Service Owner / Assigned Agent / Assignment Group**, with permissions controlling actions.

---

## 11. Ticket → Task Policy

Possible modes:

- `ALWAYS`
- `MANUAL`
- `BY_PRIORITY`
- `BY_SERVICE`
- `NEVER`

A future Bitrix24 adapter may consume a Task request, but Ticket remains the System of Record.

---

## 12. Billing / Timesheet Policy

Billing can be disabled for MVP while keeping the data model extensible.

```text
billing_enabled = false
```

When enabled, possible chain:

```text
Ticket
 ↓
Worklog
 ↓
Timesheet
 ↓
Billable Rule
 ↓
Rate
 ↓
Charge
```

Do not expose billing UI when the feature is disabled unless explicitly configured.

---

## 13. Contract Document Visibility

Visibility must be enforced at backend authorization level.

Possible policies:

- `INTERNAL_ONLY`
- `CUSTOMER_VIEW`
- `CUSTOMER_DOWNLOAD`
- `METADATA_ONLY`

Document classes may have separate policies, for example:

```text
Signed Contract       → CUSTOMER_DOWNLOAD
Pricing Appendix      → INTERNAL_ONLY
Technical Appendix    → CUSTOMER_VIEW
Internal SLA Notes    → INTERNAL_ONLY
```

Never rely only on hiding a Download button in CSS/JavaScript.

---

## 14. Portal Decision

There are two experiences inside one Product:

```text
                    ONE PRODUCT
                         │
               Shared Design System
                         │
          ┌──────────────┴──────────────┐
          │                             │
   Customer Portal                 Service Portal
          │                             │
          └──────────────┬──────────────┘
                         │
                    SAME CORE DATA
```

Customer Portal is customer-facing.

Service Portal is used by authorized company/service-team users. It is not restricted to the IT department.

Visible navigation, fields, records and actions are controlled by:

```text
User
 ↓
Role
 ↓
Permission
 ↓
Scope
 ↓
Field Visibility
 ↓
Action Authorization
```

Customer and internal users may see the same Ticket through different projections. Internal notes and other restricted data must never be returned to Customer Portal APIs.

---

## 15. Bitrix24 Decision

Bitrix24 REST integration is **Future / Optional** and is not part of the Core Product MVP.

Architecture boundary:

```text
ITSM Ticket Core
      │
      ▼
Integration Adapter
      │
      └── Bitrix24 Adapter (future)
```

Core Ticket workflow must work with zero Bitrix24 dependency.

---

## 16. Configuration Versioning

Business configuration that affects historical behavior should support effective dates or versions.

Example:

```text
Alert Profile v1
90 / 60 / 30
Effective 2026-01-01

Alert Profile v2
120 / 60 / 30
Effective 2027-01-01
```

Existing contracts/tickets must retain the effective policy needed to explain historical behavior.

---

## 17. Traceability Rule

Every important Product decision should be traceable through:

```text
Decision ID
   ↓
Requirement / Spec
   ↓
UI/UX
   ↓
Database / Configuration
   ↓
Code
   ↓
Test Case
   ↓
Git Commit / Release
```

When a decision changes, create a new decision record or mark the old one `SUPERSEDED`; do not silently rewrite history.

---

## 18. Current Status

As of this document revision:

- DEC-001 → DEC-010: `CONFIGURABLE`
- DEC-011 Bitrix24: `CONFIGURABLE / FUTURE`
- DEC-012 Portal Experience: `APPROVED ARCHITECTURE`

Business values remain open until BOD/Product explicitly approves them.

---

## 19. Development Rule

No next module is considered complete merely because PHP syntax passes.

Definition of Done:

```text
Analysis
 ↓
Implementation
 ↓
PHP Lint
 ↓
DB/Migration Test
 ↓
Functional Test
 ↓
Permission Test
 ↓
Business Rule Test
 ↓
UI/UX Check
 ↓
Regression
 ↓
GitHub Actions GREEN
 ↓
Commit / Tag
 ↓
Next Module
```

This register is the reference document for comparing future code against the original Product decisions.

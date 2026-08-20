# Module 11 — CMDB Management

## 1. Mục đích

CMDB là lớp dữ liệu cấu hình của MSP, giúp biết Customer đang sử dụng những Configuration Item (CI) nào, CI thuộc Service nào và các CI liên quan với nhau ra sao.

CMDB không thay thế Asset Management. CMDB tập trung vào **configuration, relationship và service impact**.

## 2. Giá trị nghiệp vụ

- Ticket có thể xác định CI bị ảnh hưởng.
- Problem có thể xác định CI/Service liên quan và root-cause candidate.
- Change có thể đánh giá impact trước khi triển khai.
- IT Owner/IT Lead nhìn được service dependency.
- Customer Portal chỉ hiển thị CI được phép công khai.
- Là nền tảng cho impact analysis, SLA reporting và Automation/AI sau này.

## 3. Actors

| Actor | Quyền chính |
|---|---|
| BOD/Admin | Quản trị CMDB toàn hệ thống |
| IT Lead | Quản lý CI và relationship trong scope |
| IT Owner | Xem/cập nhật CI thuộc customer/service được giao |
| IT Support | Xem CI cần xử lý; cập nhật theo permission |
| Customer | Chỉ xem CI/customer-visible được phép |

## 4. CI model

Một CI tối thiểu gồm:

- id
- customer_id
- service_id nullable
- ci_type
- name
- code
- status
- environment
- hostname
- ip_address
- fqdn
- manufacturer
- model
- serial_number
- owner_user_id nullable
- description
- criticality
- customer_visible
- metadata_json
- created_at / updated_at

CI types có thể mở rộng: SERVER, NETWORK, FIREWALL, DATABASE, APPLICATION, CLOUD_RESOURCE, ENDPOINT, OTHER.

## 5. Database implementation

Base schema dùng các bảng nghiệp vụ chung như `customers`, `services`, `users`, `contracts` và `tickets`. Schema hiện tại tạo database production `msp_itsm`; CI không được import schema bằng cách giữ nguyên câu `USE msp_itsm`, vì test phải chạy độc lập trên `msp_itsm_test`.

CMDB migration: `database/migrations/008_cmdb_management.sql`.

Các bảng CMDB:

| Table | Mục đích |
|---|---|
| `cmdb_ci_types` | Danh mục loại CI |
| `cmdb_cis` | Danh sách Configuration Item |
| `cmdb_ci_relationships` | Quan hệ giữa các CI |
| `cmdb_ci_audit` | Audit thay đổi CI |

Migration khởi tạo các CI type chuẩn: SERVER, NETWORK, FIREWALL, DATABASE, APPLICATION, CLOUD_RESOURCE, ENDPOINT và OTHER.

## 6. Relationship model

Relationship gồm:

- source_ci_id
- target_ci_id
- relationship_type
- status

Ví dụ:

`ERP Application --RUNS_ON--> Application Server`

`Application Server --USES--> Database`

`Database --HOSTED_ON--> Server`

Không cho self-reference và không cho duplicate active relationship.

## 7. Lifecycle

```text
PLANNED -> ACTIVE -> MAINTENANCE -> RETIRED
                 \\-> DISPOSED
```

Không cho CI đã DISPOSED quay lại ACTIVE bằng generic transition.

## 8. Scope & security

Internal user chỉ truy cập CI trong customer/service scope được cấp.

Customer Portal chỉ truy cập:

- customer_id của chính mình
- `customer_visible = 1`
- CI đang ACTIVE/MAINTENANCE nếu policy cho phép

Không expose filesystem path, internal credential hoặc secret trong metadata.

## 9. Links với các module

```text
Customer
   |
Contract -- Service
   |
  CMDB
   |
   +-- CI
   |    +-- Ticket
   |    +-- Problem
   |    +-- Change
   |
Knowledge
```

Ticket/Problem/Change chỉ lưu reference tới CI; không copy toàn bộ CI snapshot vào từng nghiệp vụ trừ khi audit cần snapshot.

## 10. UI/UX

### CI List

- Search
- Customer filter
- Service filter
- CI type
- Status
- Criticality
- Customer-visible
- Pagination

### CI Detail

Tabs:

1. Overview
2. Relationships
3. Tickets
4. Problems
5. Changes
6. Knowledge
7. Audit

### Relationship editor

- Chọn source CI
- Chọn relationship type
- Chọn target CI
- Validate scope
- Validate self-reference
- Confirm

## 11. Audit

Audit mọi thay đổi quan trọng:

- Create/update CI
- Status change
- Relationship create/remove
- Ownership change
- Customer visibility change

## 12. Test strategy

Module 11 có ba lớp kiểm thử:

1. **PHP Lint** — kiểm tra syntax của policy, service và test.
2. **CMDB Validation** — kiểm tra policy/lifecycle/relationship rules độc lập với MySQL.
3. **CMDB MySQL Integration** — chạy service thật với MySQL 8.0, kiểm tra create CI, transition, relationship và audit.

Integration test tự tạo fixture Customer bằng code `CMDB-TEST-CUSTOMER`, vì test không được phụ thuộc vào dữ liệu seed của module khác.

## 13. CI/CD

Workflow: `.github/workflows/cmdb-tests.yml`.

CI sử dụng MySQL 8.0 với database `msp_itsm_test`.

Schema được cài vào database test bằng cách loại bỏ hai câu production-specific:

```text
CREATE DATABASE IF NOT EXISTS msp_itsm ...
USE msp_itsm;
```

Sau đó import vào `msp_itsm_test`.

CI xác minh base schema có đủ 14 bảng trước khi cài CMDB migration. Đây là điều kiện để tránh lỗi kiểu:

```text
SQLSTATE[42S02]: Base table or view not found
Table 'msp_itsm_test.customers' doesn't exist
```

CMDB Integration chỉ được xem là PASS khi toàn bộ các bước sau GREEN:

```text
MySQL ready
    ↓
Base schema installed
    ↓
Schema verification
    ↓
CMDB migration
    ↓
PHP Lint
    ↓
CMDB Validation
    ↓
CMDB MySQL Integration
```

## 14. Acceptance Criteria

- Tạo được CI thuộc Customer/Service hợp lệ.
- Không tạo CI với Customer/Service không thuộc scope.
- Không cho self relationship.
- Không cho duplicate active relationship.
- Customer chỉ xem CI customer-visible của mình.
- Internal user không vượt scope.
- CI link được từ Ticket/Problem/Change.
- Lifecycle được kiểm soát bởi policy.
- Mọi thay đổi quan trọng có audit.
- Integration test tự seed fixture và không phụ thuộc dữ liệu ngoài test.
- MySQL integration test phải PASS.
- GitHub Actions phải GREEN trước khi đóng module.

## 15. Implementation status

| Hạng mục | Trạng thái |
|---|---|
| CMDB Policy | ✅ |
| CMDB Service | ✅ |
| CMDB Migration | ✅ |
| CMDB Validation | ✅ |
| MySQL Integration Test | 🔧 đang hoàn thiện CI fixture/schema |
| CI Schema Isolation | ✅ đã sửa |
| Test Customer Fixture | ✅ đã bổ sung |
| Documentation | ✅ |

**Module 11 chỉ được đánh dấu hoàn thành chính thức sau khi GitHub Actions chạy lại và toàn bộ CMDB tests GREEN.**

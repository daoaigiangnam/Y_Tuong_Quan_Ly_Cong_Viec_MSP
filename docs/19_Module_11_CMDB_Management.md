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

## 5. Relationship model

Relationship gồm:

- parent_ci_id
- child_ci_id
- relationship_type

Ví dụ:

`ERP Application --RUNS_ON--> Application Server`

`Application Server --USES--> Database`

`Database --HOSTED_ON--> Server`

Không cho self-reference và không cho duplicate active relationship.

## 6. Lifecycle

```text
PLANNED -> ACTIVE -> MAINTENANCE -> RETIRED
                 \-> DISPOSED
```

Không cho CI đã DISPOSED quay lại ACTIVE bằng generic transition.

## 7. Scope & security

Internal user chỉ truy cập CI trong customer/service scope được cấp.

Customer Portal chỉ truy cập:

- customer_id của chính mình
- `customer_visible = 1`
- CI đang ACTIVE/MAINTENANCE nếu policy cho phép

Không expose filesystem path, internal credential hoặc secret trong metadata.

## 8. Links với các module

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

## 9. UI/UX

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

## 10. Audit

Audit mọi thay đổi quan trọng:

- Create/update CI
- Status change
- Relationship create/remove
- Ownership change
- Customer visibility change

## 11. Acceptance Criteria

- Tạo được CI thuộc Customer/Service hợp lệ.
- Không tạo CI với Customer/Service không thuộc scope.
- Không cho self relationship.
- Không cho duplicate active relationship.
- Customer chỉ xem CI customer-visible của mình.
- Internal user không vượt scope.
- CI link được từ Ticket/Problem/Change.
- Lifecycle được kiểm soát bởi policy.
- Mọi thay đổi quan trọng có audit.
- MySQL integration test phải PASS.
- GitHub Actions phải GREEN trước khi đóng module.

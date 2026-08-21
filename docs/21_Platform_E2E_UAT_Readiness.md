# Platform E2E & UAT Readiness

## 1. Mục tiêu

Sau khi các module nghiệp vụ đã có policy, service, migration, validation và integration tests riêng, bước tiếp theo là kiểm tra **platform-level traceability**: dữ liệu và quan hệ nghiệp vụ phải đi xuyên suốt qua các module thay vì chỉ PASS độc lập.

Mục tiêu của tài liệu này là làm rõ tiêu chí để chuyển từ **Module Complete** sang **Platform UAT Ready**.

## 2. End-to-End business flow

```text
Customer
   |
   +-- Contract
   |     |
   |     +-- Service
   |
   +-- Ticket
         |
         +-- Problem
         |
         +-- Change
         |
         +-- CMDB / CI relationship
         |
         +-- Task / Task lifecycle
         |
         +-- Knowledge article / traceability
```

## 3. Automated platform smoke test

Workflow:

`.github/workflows/platform-integration-tests.yml`

Test:

`tests/platform_integration_test.php`

CI dùng MySQL 8.0 và PHP 8.3. Test tự tạo fixture trong một transaction và rollback sau khi kiểm tra, vì vậy không làm bẩn database CI.

## 4. Các assertion chính

### Customer / Contract / Service

- Customer tồn tại.
- Contract thuộc đúng Customer.
- Contract được bind đúng Service.
- Ticket tham chiếu đúng Customer + Contract + Service.

### Ticket / Problem

- Problem được tạo trong cùng Customer/Service scope.
- Problem được link với Ticket.

### Change

- Change được tạo với Customer/Service context.
- Change được link với Ticket.
- Change được link với Problem.
- Change history được ghi nhận.

### CMDB

- CI thuộc Customer/Service hợp lệ.
- Hai CI có relationship hợp lệ.
- Relationship được kiểm tra persistence.

### Task

- Ticket Assignment tạo Task.
- Task chuyển `IN_PROGRESS`.
- Task chuyển `DONE`.
- Task giữ reference tới Ticket.
- Task history được ghi nhận.

### Knowledge

- Knowledge article được tạo.
- Article được publish.
- Knowledge history được ghi nhận.
- Article được link tới Ticket.
- Article được link tới Change.

## 5. Traceability acceptance

Platform được xem là đạt E2E smoke test khi chuỗi sau tồn tại và truy vết được:

```text
Customer
  -> Contract
  -> Service
  -> Ticket
  -> Problem
  -> Change
  -> CMDB
  -> Task
  -> Knowledge
```

Không chỉ kiểm tra `INSERT` thành công; test phải xác minh các bảng liên kết và history tương ứng.

## 6. UAT checklist

### BOD / Business

- [ ] Customer nhìn đúng phạm vi dịch vụ.
- [ ] Ticket có thể truy ra Service/Contract.
- [ ] Problem có thể truy ra Ticket.
- [ ] Change có thể truy ra Ticket/Problem/CI.
- [ ] Task có owner và lifecycle rõ ràng.
- [ ] Knowledge có thể truy ra nghiệp vụ liên quan.

### IT Lead / IT Owner

- [ ] Scope theo Customer/Service đúng.
- [ ] CI relationship không tạo duplicate/self-reference.
- [ ] Audit/history có đủ bằng chứng.
- [ ] Task lifecycle đúng policy.
- [ ] Change traceability đầy đủ trước khi đóng Change.

### Customer Portal

- [ ] Không xem được dữ liệu Customer khác.
- [ ] Chỉ thấy dữ liệu được customer-visible/public.
- [ ] Không expose credential/secret/internal metadata.

## 7. CI/CD gate

Release candidate chỉ được chuyển sang UAT khi:

```text
All module workflows GREEN
        +
Platform Integration GREEN
        +
RBAC Validation GREEN
        +
PHP Lint GREEN
        =
UAT READY
```

## 8. Trạng thái hiện tại

- Module 11 CMDB: COMPLETE.
- Platform Integration smoke test: implemented.
- E2E coverage đã mở rộng qua Change và Knowledge.
- Bước kế tiếp sau khi CI GREEN: thực hiện UAT checklist và security/RBAC cross-module review.

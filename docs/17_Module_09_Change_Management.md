# 17 — Module 09: Change Management

## 1. Mục tiêu

Module 09 quản lý thay đổi có kiểm soát trong ITSM/MSP, đặc biệt các thay đổi có ảnh hưởng tới Customer, Service, Ticket và Problem.

Change là bản ghi System of Record cho kế hoạch, phê duyệt, triển khai, validation và rollback của một thay đổi.

## 2. Change lifecycle

```text
DRAFT
  ↓
ASSESSING
  ↓
PENDING_APPROVAL
  ↓
APPROVED
  ↓
SCHEDULED
  ↓
IMPLEMENTING
  ↓
VALIDATING
  ↓
COMPLETED
  ↓
CLOSED
```

Nhánh xử lý lỗi:

```text
IMPLEMENTING / VALIDATING
        ↓
      FAILED
        ↓
   ROLLED_BACK
        ↓
ASSESSING hoặc CLOSED
```

Không cho phép bỏ qua bước approval bằng cách chuyển trực tiếp `PENDING_APPROVAL → APPROVED` qua generic transition; phải sử dụng `ChangeService::approve()` để lưu approver và timestamp.

## 3. Change classification

- `STANDARD`: thay đổi lặp lại, rủi ro thấp, có quy trình chuẩn.
- `NORMAL`: thay đổi cần assessment và approval.
- `EMERGENCY`: thay đổi khẩn cấp, vẫn phải lưu đầy đủ plan, owner và audit/history.

Risk: `LOW / MEDIUM / HIGH / CRITICAL`.

Impact: `LOW / MEDIUM / HIGH`.

Priority: `P1 / P2 / P3 / P4`.

## 4. Dữ liệu chính

`changes` lưu:

- Change number/title/description
- Type, priority, risk, impact, status
- Customer / Service
- Requester / Owner / Approver
- Implementation plan
- Rollback plan
- Test plan
- Success criteria
- Reason
- Planned/actual timestamps
- Approval/close timestamps

`change_history` lưu toàn bộ mutation quan trọng.

`change_tickets` liên kết Change ↔ Ticket.

`change_problems` liên kết Change ↔ Problem.

## 5. Business rules

1. Không tạo Change thiếu title/description.
2. Không tạo Change thiếu implementation plan.
3. Không tạo Change thiếu rollback plan.
4. Không tạo Change thiếu success criteria.
5. Status phải thuộc policy đã định nghĩa.
6. Chỉ Change ở `PENDING_APPROVAL` mới được approve.
7. Mọi status mutation phải ghi history.
8. Change có thể liên kết nhiều Ticket/Problem nhưng mỗi cặp chỉ xuất hiện một lần.
9. Khi bắt đầu `IMPLEMENTING`, hệ thống ghi `actual_start_at`.
10. Khi `COMPLETED`, `FAILED` hoặc `ROLLED_BACK`, hệ thống ghi `actual_end_at`.
11. Khi `CLOSED`, hệ thống ghi `closed_at`.
12. Service phải hỗ trợ nested transaction bằng SAVEPOINT khi được gọi trong transaction bên ngoài.

## 6. Definition of Done

- PHP lint PASS
- Migration PASS
- Validation tests PASS
- MySQL integration PASS
- Change lifecycle PASS
- Approval gate PASS
- Ticket/Problem relationship PASS
- History/audit persistence PASS
- Regression PHP lint PASS
- GitHub Actions GREEN

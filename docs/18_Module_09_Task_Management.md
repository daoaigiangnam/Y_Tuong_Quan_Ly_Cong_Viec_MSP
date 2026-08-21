# Module 09 — Task Management

## Mục tiêu

Task là lớp thực thi công việc nội bộ được tạo từ ITSM Ticket khi policy cho phép. Ticket vẫn là System of Record; Task dùng để giao việc, theo dõi người thực hiện và lifecycle công việc.

## Task Creation Policy

Policy mặc định:

```text
CREATE_TASK_ON_SUPPORT_ASSIGNMENT = ON
trigger_event = TICKET_ASSIGNMENT
```

Luồng:

```text
Ticket
  ↓ assign Support / IT Owner
Task Creation Policy
  ↓ enabled
Task
  ↓
Assigned → In Progress → Done
```

Nếu policy disabled thì assignment không tạo Task.

## Data Model

### tasks

- `task_no`
- `ticket_id`
- `title`
- `description`
- `priority`
- `status`
- `assignee_user_id`
- `created_by_user_id`
- `due_at`
- `started_at`
- `completed_at`
- timestamps

### task_history

Lưu audit trail cho creation, assignment và status transition.

## Lifecycle

```text
NEW
 ├─→ ASSIGNED
 ├─→ IN_PROGRESS
 └─→ CANCELLED

ASSIGNED
 ├─→ IN_PROGRESS
 ├─→ BLOCKED
 └─→ CANCELLED

IN_PROGRESS
 ├─→ BLOCKED
 ├─→ DONE
 └─→ CANCELLED

BLOCKED
 ├─→ ASSIGNED
 ├─→ IN_PROGRESS
 └─→ CANCELLED
```

`DONE` và `CANCELLED` là terminal states.

## Test Coverage

- Task policy validation
- Enabled policy creates Task on Ticket assignment
- Disabled policy does not create Task
- Ticket → Task field mapping
- Assignee persistence
- Status lifecycle
- Start/completion timestamps
- Task history persistence
- MySQL integration
- PHP regression lint

## Bitrix24 Boundary

Bitrix24 integration không được làm System of Record. Khi triển khai integration, dùng adapter:

```text
ITSM Ticket
    ↕
Task Adapter
    ↕
Bitrix24 Task
```

Task nội bộ và Ticket phải tiếp tục hoạt động độc lập khi Bitrix24 unavailable.

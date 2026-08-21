# Module 09 — Task Management UI/UX

## Purpose
Task is the internal execution layer created from an ITSM Ticket when the configured Task Creation Policy requires operational work. Ticket remains the system of record for customer communication and SLA; Task tracks internal execution.

## UI principles
- Same Bootstrap/portal visual language as ITSM screens.
- Customer Portal never exposes internal Task records.
- Service-company users see Tasks according to RBAC, customer scope and assignment.
- Task detail always links to its source Ticket.
- Assignment and status changes are auditable through Task History.

## Screens

### Task List — `/tasks`
Filters: Task No, Ticket No, Customer, Assignee, Status, Priority, Due date, Overdue only.

Columns: Task No, Ticket, Customer, Title, Priority, Status, Assignee, Due, Updated, Actions.

Actions depend on RBAC: View, Assign, Start, Block, Complete, Cancel.

### Task Detail — `/tasks/{id}`
Header: task number, status, priority, Ticket/SLA reference, customer and service context.
Sections: Summary, Description, Ticket Context, Assignment, Due Date, Status Actions, Activity/History.

### Create Task
Fields: Task No (system generated when created from Ticket), Ticket, Title, Description, Priority, Assignee, Due Date. Manual creation requires a dedicated permission.

### Assignment
Show current assignee, target user and optional note. Successful assignment records `ASSIGNED` in Task History.

### Status lifecycle
`NEW → ASSIGNED → IN_PROGRESS → DONE`

Operational paths: `ASSIGNED → BLOCKED`, `IN_PROGRESS → BLOCKED`, `BLOCKED → ASSIGNED/IN_PROGRESS`, and `NEW/ASSIGNED/IN_PROGRESS/BLOCKED → CANCELLED`. DONE and CANCELLED are terminal.

## Ticket integration UX
Ticket Assignment → Task created when policy is enabled → Support user sees Task → Task links to Ticket → Task execution is tracked internally. Customer-facing resolution remains on Ticket. Internal Task History is not exposed to customers.

## RBAC
Suggested permissions: `task.view`, `task.create`, `task.assign`, `task.update`, `task.transition`, `task.cancel`, `task.admin`. Scope must be enforced server-side.

## Acceptance Criteria
- Filterable, paginated Task List.
- Task Detail exposes Ticket context.
- URL manipulation cannot bypass scope.
- Assignment and status transitions create Task History.
- Terminal Tasks cannot transition.
- Customer Portal does not expose internal Tasks.
- UI uses Bootstrap and the same portal shell as ITSM screens.

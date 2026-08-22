# 07 — Roadmap triển khai Final

## Phase 0 — Architecture & Data Foundation

Mục tiêu: chốt contract dữ liệu trước khi code Connector.

- Canonical Communication Event.
- Customer / Contact / Organization model.
- Interaction Identity.
- Conversation / Case / Requirement model.
- Evidence / Provenance.
- Audit.
- API/Event contract.

## Phase 1 — Structured Intake

Ổn định:

- Customer Portal.
- Service Portal.
- Service Request.
- Incident intake.
- Attachment.
- SLA calculation.

Đầu ra bắt buộc: mọi request Portal đi vào Unified Intake và tạo đúng Case/Requirement.

## Phase 2 — Communication Hub

Triển khai:

1. Web Chatbot.
2. Zalo.
3. Email.
4. Viber.
5. Social.

Tất cả Connector phải xuất Canonical Event.

## Phase 3 — Identity & Case Correlation

Triển khai:

- Channel identity mapping.
- Verified identity.
- Case reference.
- Thread/conversation correlation.
- Confidence scoring.
- Duplicate prevention.
- Human confirmation cho match không chắc.

## Phase 4 — Call

1. Kiểm tra PBX hiện tại.
2. Xác định CDR/API.
3. Xác định Recording access.
4. Kiểm tra dual-channel.
5. Chọn STT vendor.
6. Lưu Recording + Transcript.
7. Link Call → Conversation → Case.

## Phase 5 — AI & Requirement Governance

Triển khai:

- Intent classification.
- Requirement extraction.
- Missing information.
- Time/date normalization.
- Duplicate/case semantic detection.
- Scope drift detection.
- AI confidence.
- Requirement versioning.
- Customer confirmation.

## Phase 6 — MSP Execution

```text
Requirement
 ↓
Approved Scope
 ↓
Work Order
 ↓
Task Assignment
 ↓
Execution
 ↓
Evidence
 ↓
Verification
 ↓
Customer Acceptance
 ↓
Close
```

## Phase 7 — Optimization

KPI:

- Duplicate Case Rate.
- Correct Correlation Rate.
- Requirement Correction Rate.
- Evidence Coverage.
- Scope Leakage Rate.
- SLA Compliance.
- First Response Time.
- Resolution Time.
- Call QA Score.
- CSAT.
- Reopen Rate.

## PoC ưu tiên

Nên PoC trước một use case có thể kiểm chứng end-to-end:

```text
Customer Portal
    +
Zalo
    +
Call Recording
    ↓
Communication Hub
    ↓
Identity / Correlation
    ↓
Requirement
    ↓
Customer Confirmation
    ↓
Work Order
```

PoC thành công khi chứng minh được:

1. Một Case có thể nhận nhiều kênh.
2. Không tạo duplicate Case không cần thiết.
3. Có Original Evidence.
4. Truy ngược được Data Lineage.
5. Chỉ Approved Scope được phát sinh Work Order.

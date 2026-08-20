# Customer ITSM / MSP Management Platform

> Đề xuất nền tảng quản lý khách hàng, hợp đồng, Ticket và vận hành IT cho mô hình MSP.

## Mục tiêu

Tài liệu này mô tả một nền tảng Customer IT Service Management (Customer ITSM) theo 2 lớp:

1. **Phần BOD / Management** — giải thích vì sao cần làm, lợi ích kinh doanh, kiểm soát vận hành và cách đo hiệu quả.
2. **Phần Dev / Product / UIUX** — đặc tả nghiệp vụ, workflow, UI/UX, state, quyền, dữ liệu, notification, integration và acceptance criteria để triển khai đúng.

## Hai trục nghiệp vụ chính

- **Ticket Management**: Customer Portal → IT Owner → IT Support → Resolve → Customer Confirm / Reopen → Escalation.
- **Contract Management**: Ký hợp đồng → Active → Customer Portal → cảnh báo tự động 3 lần → Renewal / Expire.

## Nguyên tắc kiến trúc

- ITSM là **System of Record** cho Customer, Contract, Service, Ticket, SLA và Audit.
- Bitrix24 là **Internal Collaboration / Task Execution**; nếu tích hợp, không tạo một “Ticket độc lập” sống riêng ở Bitrix24.
- Customer chỉ thấy dữ liệu được phép công khai trên Portal.
- Mọi trạng thái quan trọng phải có lịch sử và audit.
- Email notification phải có log, retry và chống gửi trùng.

## Tài liệu

- [01 — BOD / Business Case](docs/01_BOD_Business_Case.md)
- [02 — Dev / Product / UIUX Specification](docs/02_DEV_UIUX_Specification.md)
- [03 — Data Model & Technical Blueprint](docs/03_Data_Model_Technical_Blueprint.md)
- [04 — Traceability & Acceptance Criteria](docs/04_Traceability_Acceptance_Criteria.md)
- [05 — Open Questions & Implementation Phases](docs/05_Open_Questions_Phases.md)

## Hình ảnh

Các sơ đồ được đặt trong `docs/images/` và có thể nhúng trực tiếp trong Markdown.

# 03 — Identity Resolution & Case Correlation

## 1. Identity không phụ thuộc Contract

```text
Interaction Identity
   ↓
Contact / Organization
   ↓
Prospect / Customer
   ↓
Contract
```

Khách vãng lai vẫn có thể tạo Interaction Identity và Case trước khi có Customer/Contract.

## 2. Mapping Channel Identity

Ví dụ:

```text
CUS-00025
├── Portal User ID = USER-123
├── Email = support@abc.com
├── Phone = 0901234567
├── Zalo ID = ZL98765
├── Viber ID = VB12345
└── Facebook PSID = FB88888
```

Lưu tại `interaction_identities` / identity mapping table.

Không dùng tên người làm khóa chính để mapping.

## 3. Case Correlation priority

### Level 1 — Explicit reference

- Case ID
- Request number
- Ticket number
- Conversation ID

### Level 2 — Thread relationship

- Email Message-ID / In-Reply-To / References
- Existing Conversation ID

### Level 3 — Verified Identity

- Verified phone
- Verified email
- Channel account ID
- Portal account

### Level 4 — Business context

- Service
- CI/Asset
- Location
- Department
- Current open Case
- Time window

### Level 5 — AI semantic similarity

AI chỉ được dùng khi deterministic matching chưa đủ.

## 4. Confidence model

Khuyến nghị bắt đầu:

```text
> 0.90   AUTO LINK
0.70-0.90  NEED CONFIRMATION
< 0.70   MANUAL REVIEW / NEW INTAKE
```

Cần calibration trên dữ liệu thực tế trước production.

## 5. Không tự động link khi mơ hồ

Ví dụ khách nói:

> “Cái vụ hôm trước làm tiếp giúp anh.”

Nếu có nhiều Case mở, hệ thống phải hỏi:

> “Anh đang trao đổi về CASE-00120 — Website bị chậm đúng không ạ?”

## 6. Case Correlation Output

```json
{
  "customer_id": "CUS-00025",
  "conversation_id": "CON-000888",
  "matched_case_id": "CASE-00125",
  "decision": "LINK",
  "confidence": 0.96,
  "evidence": [
    "verified_phone",
    "same_service",
    "open_case",
    "semantic_match"
  ]
}
```

## 7. Duplicate prevention

Mọi channel event đi qua Correlation trước khi tạo Case mới.

```text
New Event
   ↓
Identify Customer
   ↓
Find Active Conversations/Cases
   ↓
Match
  ├── Existing → Link
  └── No Match → New Intake
```
